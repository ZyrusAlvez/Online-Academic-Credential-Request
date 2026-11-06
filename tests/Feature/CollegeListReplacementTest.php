<?php

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Enums\DepartmentType;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\ReplaceCollegeListSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * The college list as deployments had it before 2026-11-06, with a few of its programs.
 *
 * @return Collection<string, Department>
 */
function earlierColleges(): Collection
{
    Department::factory()->registrarOffice()->create();
    Department::factory()->create(['code' => 'VPF', 'name' => 'Office of the VP for Finance']);

    $colleges = collect([
        'CAS' => 'College of Arts and Sciences', 'CAA' => 'Architecture and Aviation', 'BED' => 'Basic Education Department',
        'CBA' => 'College of Business and Accountancy', 'CCS' => 'College of Computer Studies', 'CRIM' => 'College of Criminology',
        'COED' => 'College of Education', 'COE' => 'College of Engineering', 'GS' => 'Graduate School',
        'CIHM' => 'College of International Hospitality Management', 'CME' => 'College of Maritime Education', 'LAW' => 'College of Law',
    ])->map(fn (string $name, string $code) => Department::factory()->college()->create(['code' => $code, 'name' => $name]));

    foreach (['CBA' => ['BS Accountancy', 'BS Management Accounting'], 'CAA' => ['BS Architecture'], 'LAW' => ['Juris Doctor']] as $code => $names) {
        foreach ($names as $name) {
            Program::factory()->for($colleges[$code], 'college')->create(['name' => $name]);
        }
    }

    return $colleges;
}

function replaceList(): void
{
    (new ReplaceCollegeListSeeder)->run();
}

/** Something that keeps a row from being deleted: here a log entry by the account. */
function giveHistory(User $user): void
{
    app(RecordActivity::class)->handle($user, ActivityCategory::SignIn, 'sign_in.login', 'Signed in');
}

test('REQ-02-14 each earlier college becomes its successor, keeping its requests and requesters', function () {
    $old = earlierColleges();
    $request = CredentialRequest::factory()->create(['college_id' => $old['CBA']->id]);
    $requester = User::factory()->withProfile($old['CRIM'])->create();

    replaceList();

    expect(Department::colleges()->pluck('name', 'code')->sortKeys()->all())->toBe(collect(DepartmentSeeder::COLLEGES)->sortKeys()->all())
        ->and($old['CBA']->fresh())->code->toBe('CBAA')->name->toBe('College of Business Administration and Accountancy')->is_active->toBeTrue()
        ->and($old['CRIM']->fresh())->code->toBe('CCJE')->name->toBe('College of Criminal Justice Education')
        ->and($old['CAA']->fresh()->code)->toBe('SOA')
        ->and($request->fresh()->college_id)->toBe($old['CBA']->id)
        ->and($requester->fresh()->college_id)->toBe($old['CRIM']->id)
        // Offices are left alone.
        ->and(Department::where('type', DepartmentType::Office)->pluck('code')->sort()->values()->all())->toBe(['REG', 'VPF']);
});

test('REQ-02-14 requesters of BS Architecture move to Engineering and Architecture', function () {
    $old = earlierColleges();
    $architect = User::factory()->withProfile($old['CAA'])->create(['program' => 'BS Architecture']);
    $pilot = User::factory()->withProfile($old['CAA'])->create(['program' => 'BS Aircraft Maintenance and Technology']);

    replaceList();

    expect($architect->fresh())->college_id->toBe($old['COE']->id)->program->toBe('Bachelor of Science in Architecture')
        ->and($pilot->fresh())->college_id->toBe($old['CAA']->id)->program->toBe('Bachelor of Science in Aircraft Maintenance and Technology');
});

test('REQ-02-14 the College of Law is removed', function () {
    $old = earlierColleges();

    replaceList();

    expect(Department::find($old['LAW']->id))->toBeNull()
        ->and(Department::colleges()->count())->toBe(11);
});

test('REQ-02-14 the College of Law is only deactivated while a requester still names it', function () {
    $old = earlierColleges();
    $lawyer = User::factory()->withProfile($old['LAW'])->create();

    replaceList();

    expect($old['LAW']->fresh()->is_active)->toBeFalse()
        ->and($lawyer->fresh()->college_id)->toBe($old['LAW']->id)
        ->and(Department::active()->colleges()->count())->toBe(11);
});

test('REQ-02-14 the earlier colleges\' staff accounts are removed; one that acted is deactivated', function () {
    $old = earlierColleges();
    $unused = User::factory()->departmentStaff($old['CBA'])->create();
    $acted = User::factory()->departmentStaff($old['CCS'])->create();
    giveHistory($acted);
    $finance = User::factory()->departmentStaff(Department::where('code', 'VPF')->sole())->create();

    replaceList();

    expect(User::find($unused->id))->toBeNull()
        ->and($acted->fresh()->is_active)->toBeFalse()
        ->and($finance->fresh()->is_active)->toBeTrue()
        // Production gets no new accounts: the registrar creates them.
        ->and(User::where('role', Role::Department)->where('is_active', true)->pluck('id')->all())->toBe([$finance->id]);
});

test('REQ-02-14 in local and staging every college keeps or gets one demo staff account', function () {
    $this->app['env'] = 'staging';
    $old = earlierColleges();
    $crim = User::factory()->departmentStaff($old['CRIM'])->create(['email' => 'crim@example.com']);
    giveHistory($crim);
    $law = User::factory()->departmentStaff($old['LAW'])->create(['email' => 'law@example.com']);
    $real = User::factory()->departmentStaff($old['CCS'])->create(['email' => 'dean.ccs@uphsl.edu.ph']);

    replaceList();
    $this->app['env'] = 'testing';

    $demo = User::where('role', Role::Department)->where('is_active', true)->with('department')->get();
    expect($demo->map(fn (User $staff) => [$staff->email, $staff->department->code])->sortBy(0)->values()->all())
        ->toBe(collect(DepartmentSeeder::COLLEGES)->keys()->map(fn (string $code) => [strtolower($code).'@example.com', $code])->sortBy(0)->values()->all())
        // The CRIM account carries on as CCJE's, with its history.
        ->and($crim->fresh())->email->toBe('ccje@example.com')->is_active->toBeTrue()
        ->and(Hash::check('password', $crim->fresh()->password))->toBeTrue()
        ->and(User::find($law->id))->toBeNull()
        ->and(User::find($real->id))->toBeNull();
});

test('REQ-12-12 the earlier programs are replaced and saved programs take their new names', function () {
    $old = earlierColleges();
    $renamed = User::factory()->withProfile($old['CBA'])->create(['program' => 'BS Accountancy']);
    $dropped = User::factory()->withProfile($old['CBA'])->create(['program' => 'BS Management Accounting']);
    $request = CredentialRequest::factory()->create(['college_id' => $old['CBA']->id, 'program' => 'BS Accountancy']);

    replaceList();

    $expected = collect(ProgramSeeder::PROGRAMS)->flatMap(fn (array $names, string $code) => array_map(fn ($name) => "{$code}: {$name}", $names))->sort()->values()->all();
    expect(Program::with('college')->get()->map(fn (Program $p) => "{$p->college->code}: {$p->name}")->sort()->values()->all())->toBe($expected)
        ->and(Program::count())->toBe(27)
        ->and(Program::where('name', 'Juris Doctor')->exists())->toBeFalse()
        ->and($renamed->fresh()->program)->toBe('Bachelor of Science in Accountancy')
        // Not offered any more: it stays on the profile (REQ-12-09).
        ->and($dropped->fresh()->program)->toBe('BS Management Accounting')
        // A request keeps what it was made with.
        ->and($request->fresh()->program)->toBe('BS Accountancy');
});

test('REQ-02-14 a fresh install already has the new list and running it again changes nothing', function () {
    (new DepartmentSeeder)->run();
    (new ProgramSeeder)->run();

    replaceList();
    replaceList();

    expect(Department::colleges()->count())->toBe(11)
        ->and(Department::count())->toBe(15)
        ->and(Program::count())->toBe(27);
});
