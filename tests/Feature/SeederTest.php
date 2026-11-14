<?php

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\NonWorkingDay;
use App\Models\Program;
use App\Models\Purpose;
use App\Models\User;
use App\Models\WorkflowStep;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\PurposeSeeder;
use Illuminate\Support\Facades\DB;

test('REQ-18-11 normal seeding creates the first admin and no other account', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::sole();
    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->email)->toBe(config('uphsl.initial_admin.email'))
        ->and(Department::count())->toBeGreaterThan(0);
});

test('REQ-23-02 normal seeding loads the base data every environment needs and nothing else', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::count())->toBe(15)
        ->and(Program::count())->toBe(collect(ProgramSeeder::PROGRAMS)->flatten()->count())
        ->and(Purpose::count())->toBe(count(PurposeSeeder::PURPOSES))
        ->and(CredentialType::count())->toBe(17)
        ->and(CredentialType::doesntHave('steps')->count())->toBe(0)
        ->and(NonWorkingDay::count())->toBeGreaterThan(0)
        ->and(User::count())->toBe(1)
        ->and(CredentialRequest::count())->toBe(0);
});

test('REQ-23-03 seeding the base data again adds nothing and keeps what the admin changed', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn (): array => [
        Department::count(), Program::count(), Purpose::count(), CredentialType::count(),
        WorkflowStep::count(), NonWorkingDay::count(), User::count(),
    ];
    $before = $counts();
    $holiday = NonWorkingDay::orderBy('date')->firstOrFail();

    // What an admin would change in the app.
    DB::table('departments')->where('code', 'CCS')->update(['name' => 'College of Computing']);
    DB::table('non_working_days')->where('id', $holiday->id)->update(['name' => 'Renamed holiday']);
    DB::table('credential_types')->where('name', 'Authentication')->update(['fee' => '75.00']);
    DB::table('purposes')->where('name', PurposeSeeder::PURPOSES[0])->update(['is_active' => false]);

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($before)
        ->and(Department::where('code', 'CCS')->sole()->name)->toBe('College of Computing')
        ->and($holiday->fresh()->name)->toBe('Renamed holiday')
        ->and(CredentialType::where('name', 'Authentication')->sole()->fee)->toBe('75.00')
        ->and(Purpose::where('name', PurposeSeeder::PURPOSES[0])->sole()->is_active)->toBeFalse();
});
