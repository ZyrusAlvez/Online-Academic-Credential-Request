<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RouteTask;
use App\Enums\VisitPurpose;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\Program;
use App\Models\User;
use App\Services\RequestWorkflow;
use Database\Seeders\PurposeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
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
 * Registrar-only credential. Also installs the starter purposes (spec 13).
 *
 * @return object{registrarOffice: Department, vpf: Department, chancellor: Department, ccs: Department, cba: Department, tor: CredentialType, free: CredentialType}
 */
function organisation(): object
{
    (new PurposeSeeder)->run();

    $registrarOffice = Department::factory()->registrarOffice()->create();
    $vpf = Department::factory()->create(['name' => 'Office of the VP for Finance', 'code' => 'VPF']);
    $chancellor = Department::factory()->create(['name' => 'Office of the Chancellor', 'code' => 'CHAN']);
    $ccs = Department::factory()->college()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
    $cba = Department::factory()->college()->create(['name' => 'College of Business and Accountancy', 'code' => 'CBA']);

    // The program of User::factory()->withProfile(), so a saved profile passes New Request (REQ-15-03).
    foreach ([$ccs, $cba] as $college) {
        Program::factory()->for($college, 'college')->create(['name' => 'BS Computer Science']);
    }

    $tor = CredentialType::factory()->paid('530.00')
        ->withSteps([[$registrarOffice, RouteTask::Prepare], 'college', [$vpf, RouteTask::DocumentaryStamp], $chancellor])
        ->create(['name' => 'Transcript of Records', 'processing_days' => 7, 'requirements' => ['Completed clearance', 'PSA-authenticated birth certificate']]);

    $free = CredentialType::factory()
        ->withSteps([[$registrarOffice, RouteTask::Prepare]])
        ->create(['name' => 'Certification of Enrollment', 'processing_days' => 3]);

    return (object) compact('registrarOffice', 'vpf', 'chancellor', 'ccs', 'cba', 'tor', 'free');
}

/**
 * A requester with a complete profile and a saved valid ID.
 */
function requester(Department $college): User
{
    return User::factory()->withProfile($college)->create();
}

/**
 * New Request's details card of one credential, from its opening tag to the next card (REQ-15-15).
 */
function credentialCard(string $html, CredentialType $type): string
{
    preg_match('/<div[^>]*data-credential-card="'.$type->id.'".*?(?=<div[^>]*data-credential-card=|data-receiving-school)/s', $html, $card);

    return $card[0] ?? '';
}

/**
 * Whether that card starts visible (the browser then follows the dropdown, REQ-15-15).
 */
function credentialCardShown(string $html, CredentialType $type): bool
{
    $card = credentialCard($html, $type);

    return $card !== '' && ! str_contains(Str::before($card, '>'), 'display: none');
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
        'purpose' => 'Employment',
        'receiving_institution' => null,
    ], $overrides));
}

/**
 * Give every part of a stage's checklist the same result (REQ-16-10, 17, 19).
 */
function checkAll(CredentialRequest $request, User $registrar, CheckStage $stage, CheckResult $result = CheckResult::Confirmed): void
{
    foreach (CheckItem::for($stage, $request->fresh()) as $item) {
        workflow()->recordCheck($request, $registrar, $item, $result);
    }
}

/**
 * Evaluate a submitted request: every part confirmed, then approved, which verifies its ID
 * (REQ-16-12).
 */
function evaluate(CredentialRequest $request, User $registrar): CredentialRequest
{
    workflow()->startEvaluation($request, $registrar);
    checkAll($request, $registrar, CheckStage::Evaluation);
    workflow()->approveEvaluation($request, $registrar);

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
        checkAll($request, $registrar, CheckStage::Payment);
        workflow()->verifyPaymentAndAccept($request, $registrar, 'OR-0001');
    }

    return $request->fresh();
}

function openVisit(CredentialRequest $request): OfficeVisit
{
    return $request->openVisit()->with(['department', 'step', 'credentialRequest'])->firstOrFail();
}

/**
 * Accept receipt of the open visit and complete it; the final quality check needs its checklist
 * confirmed first (REQ-16-19).
 */
function passOffice(CredentialRequest $request, User $actor): void
{
    $visit = openVisit($request);
    workflow()->acceptReceipt($visit, $actor);

    if ($visit->step->task === RouteTask::FinalCheck && $visit->purpose !== VisitPurpose::Correction) {
        checkAll($request, $actor, CheckStage::FinalCheck);
    }

    workflow()->completeVisit($visit, $actor);
}
