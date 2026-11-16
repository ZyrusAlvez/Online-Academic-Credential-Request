<?php

use App\Enums\CheckStage;
use App\Enums\RecipientType;
use App\Enums\RegistrarQueue;
use App\Enums\RequestChannel;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Queries\StageQueue;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->cashier = User::factory()->cashier()->create();
});

/** An application for a TOR (paid, four offices) and a certification (free, the Registrar only). */
function twoCredentials(): CredentialRequest
{
    return workflow()->submit(test()->owner, [
        ['credential_type_id' => test()->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null],
        ['credential_type_id' => test()->org->free->id, 'purpose' => 'Scholarship application', 'receiving_institution' => null],
    ]);
}

function second(CredentialRequest $lead): CredentialRequest
{
    return CredentialRequest::where('application_id', $lead->application_id)->where('item_no', 2)->sole();
}

// ── Applications and tracking numbers ───────────────────────────────────────────────────────

test('REQ-27-01 an application has its own number and each credential adds its position; the total is due once', function () {
    $lead = twoCredentials();
    $application = $lead->application;

    expect($application->reference_no)->toBe(sprintf('rc%s-%05d', now()->format('y'), $application->id))
        ->and($application->credentialRequests->pluck('reference_no')->all())->toBe([$application->reference_no.'-1', $application->reference_no.'-2'])
        ->and($application->credentialRequests->pluck('credential_type_id')->all())->toBe([$this->org->tor->id, $this->org->free->id])
        ->and($lead)->isLead()->toBeTrue()->amount_due->toBe('530.00')->requires_payment->toBeTrue()
        ->and(second($lead))->isLead()->toBeFalse()->fee->toBeNull()->status->toBe(RequestStatus::Submitted)
        ->and($application->user_id)->toBe($this->owner->id);
});

test('REQ-27-01 the same credential cannot be in one application twice', function () {
    expect(fn () => workflow()->submit($this->owner, [
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'],
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Further studies'],
    ]))->toThrow(InvalidTransition::class, 'only once');

    expect(CredentialRequest::count())->toBe(0);
});

test('REQ-27-02 a request numbered before applications keeps its number and its links', function () {
    // As the migration leaves them: an application of one credential, both with the old number.
    $request = CredentialRequest::factory()->create();
    $request->application->update(['reference_no' => 'cr26-00007']);
    $request->forceFill(['reference_no' => 'cr26-00007'])->save();

    $this->actingAs($this->registrar)->get(route('records.show', $request->fresh()))->assertOk()->assertSee('cr26-00007');
    expect($request->fresh()->application->credentialRequests)->toHaveCount(1);
});

// ── Until acceptance: one application ───────────────────────────────────────────────────────

test('REQ-27-03 until acceptance the application is evaluated once, on its first credential, and the others follow', function () {
    $lead = twoCredentials();
    $other = second($lead);

    // One row in Initial Evaluation.
    expect(StageQueue::for(RegistrarQueue::Evaluation)->pluck('id')->all())->toBe([$lead->id]);

    // The other credential cannot be acted on by itself.
    expect(fn () => workflow()->startEvaluation($other, $this->registrar))->toThrow(InvalidTransition::class, '-1');

    workflow()->startEvaluation($lead, $this->registrar);
    expect($other->fresh())->status->toBe(RequestStatus::UnderEvaluation)->evaluated_by->toBe($this->registrar->id)
        ->and($other->events()->count())->toBe(0);

    checkAll($lead, $this->registrar, CheckStage::Evaluation);
    workflow()->approveEvaluation($lead, $this->registrar);
    expect($other->fresh()->status)->toBe(RequestStatus::AwaitingPayment);

    // One payment for the application, validated by the cashier.
    uploadPayment($lead);
    expect($other->fresh()->status)->toBe(RequestStatus::PaymentVerification)
        ->and(StageQueue::for(RegistrarQueue::Payment, channel: RequestChannel::Online)->pluck('id')->all())->toBe([$lead->id]);
});

test('REQ-27-03 declining or cancelling the application ends every credential', function () {
    $declined = twoCredentials();
    workflow()->startEvaluation($declined, $this->registrar);
    workflow()->decline($declined, $this->registrar, 'Not a UPHSL graduate.');
    expect(second($declined)->fresh())->status->toBe(RequestStatus::Declined)->decline_reason->toBe('Not a UPHSL graduate.');

    $cancelled = workflow()->submit($this->owner, [
        ['credential_type_id' => $this->org->free->id, 'purpose' => 'Employment'],
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'],
    ]);
    workflow()->cancel($cancelled, $this->owner, 'Wrong documents.');
    expect(second($cancelled)->fresh()->status)->toBe(RequestStatus::Cancelled);
});

// ── From acceptance: each credential on its own ─────────────────────────────────────────────

test('REQ-27-04 once paid, each credential is accepted with its own route and due date and moves on its own', function () {
    $lead = acceptRequest(twoCredentials(), $this->registrar);
    $other = second($lead);

    expect($lead->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($other->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($lead->steps()->count())->toBe(5)    // Registrar, college, VPF, Chancellor, final check
        ->and($other->steps()->count())->toBe(2)   // Registrar, final check
        ->and($lead->due_at->greaterThan($other->due_at))->toBeTrue()
        ->and($other->events()->where('type', RequestEventType::Accepted)->exists())->toBeTrue();

    // Both in In Progress, one row each; the certification finishes first and is released alone.
    expect(StageQueue::for(RegistrarQueue::InProgress)->pluck('id')->sort()->values()->all())->toBe(collect([$lead->id, $other->id])->sort()->values()->all());

    passOffice($other, $this->registrar);
    if (openVisit($other)->status === VisitStatus::InTransit) {
        workflow()->acceptReceipt(openVisit($other), $this->registrar);
    }
    workflow()->approveFinalCheck($other->fresh(), $this->registrar);
    workflow()->release($other->fresh(), $this->registrar, RecipientType::Owner);

    expect($other->fresh()->status)->toBe(RequestStatus::Released)
        ->and($lead->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing);
});

test('REQ-27-05 before acceptance staff and the requester are told once per application', function () {
    Notification::fake();
    $lead = twoCredentials();

    Notification::assertSentToTimes($this->registrar, RequestActivity::class, 1);

    workflow()->startEvaluation($lead, $this->registrar);
    checkAll($lead, $this->registrar, CheckStage::Evaluation);
    workflow()->approveEvaluation($lead, $this->registrar);

    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::RequirementsCompleted);
    expect(collect(Notification::sent($this->owner, RequestActivity::class))->filter(fn ($n) => $n->event->type === RequestEventType::RequirementsCompleted))->toHaveCount(1);
});

test('REQ-27-01 a walk-in application can have several credentials too', function () {
    $lead = workflow()->submitWalkIn($this->registrar, [
        ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment'],
        ['credential_type_id' => $this->org->free->id, 'purpose' => 'Employment'],
    ], [
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'email' => 'pedro@example.com', 'contact_number' => '09171234567',
        'academic_status' => 'graduate', 'college_id' => $this->org->ccs->id, 'program' => 'BS Computer Science', 'student_number' => null,
        'attended_from' => 2018, 'attended_to' => 2022,
    ]);

    expect(second($lead)->status)->toBe(RequestStatus::PaymentVerification);

    workflow()->verifyWalkInPayment($lead, $this->registrar, 'OR-9', '530');
    expect(second($lead)->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and(CredentialType::count())->toBeGreaterThan(1);
});
