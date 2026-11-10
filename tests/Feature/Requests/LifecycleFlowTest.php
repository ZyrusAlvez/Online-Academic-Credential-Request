<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RecipientType;
use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| The client's end-to-end process flow (Proposed Workflow, section XII), walked through once.
*/

test('a TOR request follows the client\'s flowchart from portal to release', function () {
    Storage::fake('local');
    $org = organisation();
    $owner = requester($org->ccs);
    $registrar = User::factory()->registrar()->create();
    $dean = User::factory()->departmentStaff($org->ccs)->create();
    $vpf = User::factory()->departmentStaff($org->vpf)->create();
    $chancellor = User::factory()->departmentStaff($org->chancellor)->create();

    // Submit → evaluate: the purpose needs clarifying → returned → corrected and resubmitted →
    // originals missing → office visit → received → compliance confirmed (spec 20).
    $request = submitRequest($owner, $org->tor);
    workflow()->startEvaluation($request, $registrar);
    workflow()->recordCheck($request, $registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($request, $registrar, 'Name the board examination.');
    workflow()->resubmit($request, $owner, null, ['credential_type_id' => $org->tor->id, 'purpose' => 'Nursing board examination']);
    workflow()->startEvaluation($request, $registrar);
    workflow()->recordCheck($request, $registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->requireOfficeVisit($request, $registrar, ['PSA-authenticated birth certificate'], 'Submit the original.');
    workflow()->markReceived($request->deficiencies()->firstOrFail(), $registrar);
    workflow()->confirmCompliance($request, $registrar);
    expect($request->fresh())->status->toBe(RequestStatus::AwaitingPayment)->purpose->toBe('Nursing board examination');

    // Payment returned → valid proof → validated and accepted (clock starts).
    uploadPayment($request);
    workflow()->rejectPayment($request, $registrar, 'Screenshot is unreadable');
    uploadPayment($request);
    checkAll($request, $registrar, CheckStage::Payment);
    workflow()->verifyPaymentAndAccept($request, $registrar, 'OR-2026-001');
    expect($request->fresh())->status->toBe(RequestStatus::AcceptedForProcessing)->due_at->not->toBeNull();

    // Route: Registrar → Dean → VP Finance returns to Dean → corrected → VP Finance again.
    passOffice($request, $registrar);
    passOffice($request, $dean);
    workflow()->acceptReceipt(openVisit($request), $vpf);
    workflow()->returnVisit(openVisit($request), $vpf, $org->ccs, 'Missing Dean signature on page 2', 'Sign page 2');
    passOffice($request, $dean);
    passOffice($request, $vpf);
    passOffice($request, $chancellor);

    // Final quality check → ready → representative claim blocked until verified → released.
    passOffice($request, $registrar);
    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);

    $representative = declareRepresentative($request, $owner, ['full_name' => 'Rosa Dela Cruz']);
    expect(fn () => workflow()->release($request, $registrar, RecipientType::Representative, $representative->id))->toThrow(InvalidTransition::class);

    workflow()->reviewRepresentative($representative, $registrar, true);
    workflow()->release($request, $registrar, RecipientType::Representative, $representative->id);

    $request = $request->fresh();
    $visits = $request->visits()->with('department')->get();

    expect($request->status)->toBe(RequestStatus::Released)
        ->and($request->processing_working_days)->toBe(0)
        ->and($request->release->recipient_name)->toBe('Rosa Dela Cruz')
        ->and($visits->map(fn ($v) => $v->department->code.':'.$v->status->value)->all())->toBe([
            'REG:completed', 'CCS:completed', 'VPF:returned', 'CCS:completed', 'VPF:completed', 'CHAN:completed', 'REG:completed',
        ])
        ->and($visits->where('status', VisitStatus::Returned)->sole()->return_reason)->toBe('Missing Dean signature on page 2');
});
