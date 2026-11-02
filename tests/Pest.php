<?php

use App\Enums\RouteTask;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Shared fixtures for request-lifecycle tests.
*/

/**
 * A small organisation: the Registrar, VP Finance, Chancellor and two colleges, plus a TOR-like
 * credential (Registrar prepare → Dean sign → VP Finance stamp → Chancellor sign) and a free
 * Registrar-only credential.
 *
 * @return object{registrarOffice: Department, vpf: Department, chancellor: Department, ccs: Department, cba: Department, tor: CredentialType, free: CredentialType}
 */
function organisation(): object
{
    $registrarOffice = Department::factory()->registrarOffice()->create();
    $vpf = Department::factory()->create(['name' => 'Office of the VP for Finance', 'code' => 'VPF']);
    $chancellor = Department::factory()->create(['name' => 'Office of the Chancellor', 'code' => 'CHAN']);
    $ccs = Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    $cba = Department::factory()->college()->create(['name' => 'College of Business and Accountancy', 'code' => 'CBA']);

    $tor = CredentialType::factory()->paid('530.00')
        ->withSteps([[$registrarOffice, RouteTask::Prepare], 'college', [$vpf, RouteTask::DocumentaryStamp], $chancellor])
        ->create(['name' => 'Transcript of Records', 'processing_days' => 7, 'requirements' => ['Completed clearance', 'PSA-authenticated birth certificate']]);

    $free = CredentialType::factory()
        ->withSteps([[$registrarOffice, RouteTask::Prepare]])
        ->create(['name' => 'Certification of Enrollment', 'processing_days' => 3]);

    return (object) compact('registrarOffice', 'vpf', 'chancellor', 'ccs', 'cba', 'tor', 'free');
}

/**
 * A requester with a complete profile and a verified identity.
 */
function requester(Department $college): User
{
    return User::factory()->withProfile($college)->create();
}

function workflow(): RequestWorkflow
{
    return app(RequestWorkflow::class);
}

/**
 * Submit a request through the real workflow.
 *
 * @param  array<string, mixed>  $overrides
 */
function submitRequest(User $owner, CredentialType $type, array $overrides = []): CredentialRequest
{
    return workflow()->submit($owner, array_merge([
        'credential_type_id' => $type->id,
        'copies' => 1,
        'purpose' => 'Employment',
        'receiving_institution' => null,
    ], $overrides));
}

/**
 * Evaluate a submitted request as complete.
 */
function evaluate(CredentialRequest $request, User $registrar): CredentialRequest
{
    workflow()->startEvaluation($request, $registrar);
    workflow()->completeEvaluation($request, $registrar);

    return $request;
}

function uploadPayment(CredentialRequest $request): void
{
    workflow()->submitPaymentProof($request, $request->student()->firstOrFail(), UploadedFile::fake()->image('receipt.jpg'),
        'REF-123', (string) $request->amount_due, now()->format('Y-m-d'));
}

/**
 * Take a submitted request through evaluation (and payment) to acceptance.
 */
function acceptRequest(CredentialRequest $request, User $registrar): CredentialRequest
{
    evaluate($request, $registrar);

    if ($request->fresh()->requires_payment) {
        uploadPayment($request);
        workflow()->verifyPaymentAndAccept($request, $registrar, 'OR-0001');
    }

    return $request->fresh();
}

function openVisit(CredentialRequest $request): OfficeVisit
{
    return $request->openVisit()->with(['department', 'step', 'credentialRequest'])->firstOrFail();
}

/**
 * Accept receipt of the open visit and complete it.
 */
function passOffice(CredentialRequest $request, User $actor): void
{
    $visit = openVisit($request);
    workflow()->acceptReceipt($visit, $actor);
    workflow()->completeVisit($visit, $actor);
}
