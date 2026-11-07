<?php

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Enums\RouteTask;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Services\RequestWorkflow;
use Database\Seeders\RemoveCollegeOfLawSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->registrar = User::factory()->registrar()->create();
    // Active, so requests can still be filed under it; staging has it inactive, which changes nothing.
    $this->law = Department::factory()->college()->create(['name' => 'College of Law', 'code' => 'LAW']);
    Program::factory()->for($this->law, 'college')->create(['name' => 'Juris Doctor']);
    $this->lawStaff = User::factory()->departmentStaff($this->law)->create();
});

function removeLaw(): void
{
    (new RemoveCollegeOfLawSeeder)->run();
}

/** A paid TOR request filed under the college, accepted and taken to its Dean's step. */
function lawRequest(User $owner, User $registrar): CredentialRequest
{
    $request = acceptRequest(submitRequest($owner, test()->org->tor), $registrar);
    passOffice($request, $registrar);

    return $request->fresh();
}

test('REQ-02-18 the College of Law is deleted with its programs, route steps and staff accounts', function () {
    $type = CredentialType::factory()->withSteps([[$this->law, RouteTask::Sign], $this->org->chancellor])->create();
    app(RecordActivity::class)->handle($this->lawStaff, ActivityCategory::SignIn, 'sign_in.login', 'Signed in', departmentId: $this->law->id);

    removeLaw();

    $entry = ActivityLog::sole();
    expect(Department::find($this->law->id))->toBeNull()
        ->and(Program::where('name', 'Juris Doctor')->exists())->toBeFalse()
        ->and(User::find($this->lawStaff->id))->toBeNull()
        ->and(WorkflowStep::where('credential_type_id', $type->id)->pluck('department_id')->all())->toBe([$this->org->chancellor->id])
        // The log entry stays, with the copied name, but no longer links to the college or the account.
        ->and($entry)->department_id->toBeNull()->actor_id->toBeNull()->actor_name->toBe($this->lawStaff->name);
});

test('REQ-02-18 requesters who named the College of Law have their college and program cleared', function () {
    $lawyer = User::factory()->withProfile($this->law)->create(['program' => 'Juris Doctor']);
    $other = requester($this->org->ccs);

    removeLaw();

    expect($lawyer->fresh())->college_id->toBeNull()->program->toBeNull()
        ->and($other->fresh())->college_id->toBe($this->org->ccs->id)->program->toBe('BS Computer Science');
});

test('REQ-02-18 requests filed under the College of Law are deleted with every row and upload', function () {
    $lawyer = User::factory()->withProfile($this->law)->create();
    $lawRequest = lawRequest($lawyer, $this->registrar);
    $kept = acceptRequest(submitRequest(requester($this->org->ccs), $this->org->tor), $this->registrar);
    $receipt = $lawRequest->paymentProofs()->sole()->path;

    expect($lawRequest->openVisit()->sole()->department_id)->toBe($this->law->id)
        ->and(Storage::disk(RequestWorkflow::proofDisk())->exists($receipt))->toBeTrue();

    removeLaw();

    expect(CredentialRequest::find($lawRequest->id))->toBeNull()
        ->and(CredentialRequest::find($kept->id))->not->toBeNull()
        ->and($kept->events()->count())->toBeGreaterThan(0)
        ->and(Storage::disk(RequestWorkflow::proofDisk())->exists($receipt))->toBeFalse()
        ->and(DB::table('notifications')->where('data->request_id', $lawRequest->id)->exists())->toBeFalse()
        ->and(Department::find($this->law->id))->toBeNull();

    foreach (['request_events', 'request_steps', 'office_visits', 'payment_proofs', 'request_checks'] as $table) {
        expect(DB::table($table)->where('credential_request_id', $lawRequest->id)->exists())->toBeFalse("{$table} still holds the request");
    }
});

test('REQ-02-18 a request routed through the College of Law is deleted too', function () {
    $type = CredentialType::factory()->withSteps([[$this->org->registrarOffice, RouteTask::Prepare], [$this->law, RouteTask::Sign]])->create();
    $request = acceptRequest(submitRequest(requester($this->org->ccs), $type), $this->registrar);

    removeLaw();

    expect(CredentialRequest::find($request->id))->toBeNull();
});

test('REQ-02-18 running it again, or with no College of Law, changes nothing', function () {
    $request = acceptRequest(submitRequest(requester($this->org->ccs), $this->org->tor), $this->registrar);
    $counts = fn () => [Department::count(), CredentialRequest::count(), User::count(), Program::count()];

    removeLaw();
    $after = $counts();
    removeLaw();

    expect($counts())->toBe($after)
        ->and(CredentialRequest::find($request->id))->not->toBeNull()
        ->and(Department::where('code', 'CCS')->exists())->toBeTrue();
});

test('REQ-02-18 a college the registrar added is left alone', function () {
    $nursing = Department::factory()->college()->create(['name' => 'College of Nursing', 'code' => 'CON']);

    removeLaw();

    expect(Department::find($nursing->id))->not->toBeNull();
});
