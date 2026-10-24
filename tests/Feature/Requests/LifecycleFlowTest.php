<?php

use App\Enums\RecipientType;
use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

    // Submit → evaluate: requirements incomplete → comply in person → complete.
    $request = submitRequest($owner, $org->tor);
    workflow()->startEvaluation($request, $registrar);
    workflow()->requestCompliance($request, $registrar, ['PSA-authenticated birth certificate']);
    workflow()->resolveDeficiency($request->deficiencies()->sole(), $registrar, 'Original PSA birth certificate received');
    workflow()->completeEvaluation($request, $registrar);

    // Payment not verified → valid proof → verified and accepted (clock starts).
    uploadPayment($request);
    workflow()->rejectPayment($request, $registrar, 'Screenshot is unreadable');
    uploadPayment($request);
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

    $representative = workflow()->declareRepresentative($request, $owner, ['full_name' => 'Rosa Dela Cruz'], UploadedFile::fake()->image('spa.jpg'));
    $claim = [
        'recipient_type' => RecipientType::Representative,
        'representative_id' => $representative->id,
        'recipient_name' => 'Rosa Dela Cruz',
        'id_presented' => 'UMID 0001',
        'identity_checked' => true,
        'acknowledged' => true,
    ];
    expect(fn () => workflow()->release($request, $registrar, $claim))->toThrow(InvalidTransition::class);

    workflow()->reviewRepresentative($representative, $registrar, true);
    workflow()->release($request, $registrar, $claim);

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
