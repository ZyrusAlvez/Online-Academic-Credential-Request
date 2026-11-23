<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RecipientType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Cashier\Payments as CashierPayments;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show;
use App\Models\CredentialRequest;
use App\Models\RequestCheck;
use App\Models\User;
use App\Notifications\RequestActivity;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = submitRequest($this->owner, $this->org->tor);
});

function stagePage(?CredentialRequest $request = null, ?User $as = null): Testable
{
    // A fresh copy, as a page load would read it.
    return Livewire::actingAs($as ?? test()->registrar)->test(Show::class, ['credentialRequest' => ($request ?? test()->request)->fresh()]);
}

function queuePage(string $queue, array $query = []): Testable
{
    return Livewire::withQueryParams($query)->actingAs(test()->registrar)->test(Queue::class, ['queue' => $queue]);
}

/** A request taken up for evaluation, with some parts checked. */
function underEvaluation(CredentialRequest $request, array $results = []): CredentialRequest
{
    workflow()->startEvaluation($request, test()->registrar);

    foreach ($results as $item => $result) {
        workflow()->recordCheck($request, test()->registrar, CheckItem::from($item), $result);
    }

    return $request->fresh();
}

/** A paid walk-in request with its payment awaiting the registrar's validation (REQ-25-07). */
function awaitingValidation(CredentialRequest $request): CredentialRequest
{
    evaluate($request, test()->registrar);
    uploadPayment($request);

    return walkIn($request);
}

/** An accepted request taken through every office to the received final quality check. */
function atFinalCheck(CredentialRequest $request): CredentialRequest
{
    $registrar = test()->registrar;
    acceptRequest($request, $registrar);
    passOffice($request, $registrar);                                                       // Registrar prepares
    passOffice($request, User::factory()->departmentStaff(test()->org->ccs)->create());       // Dean
    passOffice($request, User::factory()->departmentStaff(test()->org->vpf)->create());       // VP Finance
    passOffice($request, User::factory()->departmentStaff(test()->org->chancellor)->create()); // Chancellor; received at once (REQ-32-09)

    return $request->fresh();
}

test('REQ-16-02 Initial Evaluation lists submitted and evaluated requests with their evaluator; returned ones wait apart', function () {
    $evaluating = underEvaluation(submitRequest($this->owner, $this->org->free));
    $returned = underEvaluation(submitRequest($this->owner, $this->org->free), [CheckItem::Identity->value => CheckResult::Rejected]);
    workflow()->returnForClarification($returned, $this->registrar, 'Blurred ID.');

    queuePage('evaluation')
        ->assertSeeInOrder([$this->request->reference_no, $evaluating->reference_no])
        ->assertSee('Evaluating: '.$this->registrar->name)
        ->assertDontSee($returned->reference_no);

    queuePage('evaluation', ['waiting' => true])
        ->assertSee($returned->reference_no)
        ->assertDontSee($this->request->reference_no);
});

test('REQ-16-03 Payment Validation lists the proofs to verify; the requests awaiting payment wait in their own tab', function () {
    // Now the cashier's list, for online requests (REQ-25-04).
    $cashier = User::factory()->cashier()->create();
    $unpaid = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar)->fresh();
    $paying = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($paying);
    $paying = $paying->fresh();

    expect($unpaid->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($paying->status)->toBe(RequestStatus::PaymentVerification);

    Livewire::actingAs($cashier)->test(CashierPayments::class)
        ->assertSee('Online payments to validate.')
        ->assertSeeHtml('data-queue-tabs')
        ->assertSeeInOrder(['To verify', 'Waiting for the requester (1)'])
        ->assertSeeInOrder([$paying->reference_no, RequestStatus::PaymentVerification->label(), '₱530.00'])
        ->assertDontSee($unpaid->reference_no)
        ->assertDontSee($this->request->reference_no);

    Livewire::withQueryParams(['waiting' => true])->actingAs($cashier)->test(CashierPayments::class)
        ->assertSeeInOrder([$unpaid->reference_no, RequestStatus::AwaitingPayment->label(), '₱530.00'])
        ->assertDontSee($paying->reference_no)
        ->assertDontSee($this->request->reference_no);

    // The registrar's Payment Validation: walk-in payments only, in a single list (REQ-25-07).
    $walkIn = awaitingValidation(submitRequest($this->owner, $this->org->tor));
    queuePage('payment')
        ->assertSee('Walk-in payments to validate.')
        ->assertDontSeeHtml('data-queue-tabs')
        ->assertSee($walkIn->reference_no)
        ->assertDontSee($paying->reference_no);

    // The other queues keep a single list.
    queuePage('release')->assertDontSeeHtml('data-queue-tabs');
});

test('REQ-16-05 Final Quality Check lists the documents back at the Registrar for the final check', function () {
    $finalCheck = atFinalCheck(submitRequest($this->owner, $this->org->tor));

    queuePage('final_check')->assertSee($finalCheck->reference_no)->assertDontSee($this->request->reference_no);
});

test('REQ-16-06 Release lists the credentials ready for release', function () {
    $ready = atFinalCheck(submitRequest($this->owner, $this->org->tor));
    workflow()->approveFinalCheck($ready, $this->registrar);

    queuePage('release')->assertSee($ready->reference_no)->assertDontSee($this->request->reference_no);
});

test('REQ-16-07 Records lists every request, searchable and filterable, and a record is read-only', function () {
    $declined = underEvaluation(submitRequest($this->owner, $this->org->free));
    workflow()->decline($declined, $this->registrar, 'Not a UPHSL graduate.');

    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)
        ->assertSee($this->request->reference_no)->assertSee($declined->reference_no)
        ->set('status', RequestStatus::Declined->value)
        ->assertSee($declined->reference_no)->assertDontSee($this->request->reference_no)
        ->set('status', '')->set('search', $this->request->reference_no)
        ->assertSee($this->request->reference_no)->assertDontSee($declined->reference_no);

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $this->request])
        ->assertSee('Tracking History')->assertSee('Request Details')
        ->assertDontSee('Start evaluation')->assertDontSee('Approve')->assertDontSee('Decline');

    $this->actingAs($this->owner)->get(route('records.show', $this->request))->assertForbidden();
});

// ── The evaluation lock ─────────────────────────────────────────────────────────────────────

test('REQ-16-09 while under evaluation only its evaluator acts; others see who has it', function () {
    stagePage()->call('startEvaluation');
    $other = User::factory()->registrar()->create(['first_name' => 'Other']);

    expect($this->request->fresh()->evaluated_by)->toBe($this->registrar->id);

    stagePage(as: $other)
        ->assertSee("{$this->request->reference_no} is under evaluation by {$this->registrar->name}.")
        ->assertDontSee('Approve')
        ->assertDontSee($this->owner->email);

    stagePage(as: $other)->call('check', CheckItem::Identity->value, CheckResult::Confirmed->value)->assertForbidden();
    stagePage(as: $other)->set('remarks', 'Mine now.')->call('decline')->assertForbidden();
    expect(fn () => workflow()->recordCheck($this->request, $other, CheckItem::Identity, CheckResult::Confirmed))->toThrow(InvalidTransition::class);

    // The lock ends with the decision.
    checkAll($this->request, $this->registrar, CheckStage::Evaluation);
    stagePage()->call('approve');
    stagePage(as: $other)->assertDontSee('is under evaluation by');
});

test('REQ-16-11 remarks are optional to approve and required to return or decline', function () {
    underEvaluation($this->request, [CheckItem::Identity->value => CheckResult::Rejected]);

    // The buttons themselves are REQ-20-02's.
    stagePage()->call('returnRequest')->assertHasErrors('remarks');
    stagePage()->call('decline')->assertHasErrors('remarks');
    // Requiring an office visit needs an item, not remarks (REQ-20-04, 2026-11-15).
    stagePage()->call('requireOfficeVisit')->assertHasErrors('requiredItems')->assertHasNoErrors('remarks');

    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);
});

test('REQ-16-12 approving needs every part confirmed, then verifies the ID and representatives', function () {
    declareRepresentative($this->request, $this->owner, ['full_name' => 'Rosa Reyes']);
    underEvaluation($this->request, [CheckItem::CredentialRequest->value => CheckResult::Confirmed, CheckItem::Identity->value => CheckResult::Confirmed]);

    stagePage()->call('approve')
        ->assertDispatched('modal-show', name: 'decision-blocked')
        ->assertSet('blockedParts', fn (array $parts) => in_array(['label' => 'Academic Record Verification', 'state' => 'unchecked'], $parts, true));
    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);

    checkAll($this->request, $this->registrar, CheckStage::Evaluation);
    stagePage()->call('approve')->assertHasNoErrors();

    expect($this->request->fresh())
        ->status->toBe(RequestStatus::AwaitingPayment)
        ->identity_status->toBe(ReviewStatus::Verified)
        ->and($this->request->representatives()->first()->status)->toBe(ReviewStatus::Verified);

    $free = submitRequest($this->owner, $this->org->free);
    evaluate($free, $this->registrar);
    expect($free->fresh())->status->toBe(RequestStatus::AcceptedForProcessing)->accepted_at->not->toBeNull();
});

test('REQ-16-14 declining ends the request with the remarks as the reason and tells the requester', function () {
    Notification::fake();
    underEvaluation($this->request);

    stagePage()->set('remarks', 'Not a UPHSL graduate.')->call('decline')->assertHasNoErrors();

    expect($this->request->fresh())->status->toBe(RequestStatus::Declined)->decline_reason->toBe('Not a UPHSL graduate.');
    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::Declined);
});

// ── In progress ─────────────────────────────────────────────────────────────────────────────

test('REQ-16-18 the request is in progress only once the paper leaves the first office', function () {
    acceptRequest($this->request, $this->registrar);
    workflow()->completeVisit($visit = openVisit($this->request), $this->registrar);   // prepared, still at the Registrar

    expect($this->request->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing);

    workflow()->forwardDocument($visit, $this->registrar);

    expect($this->request->fresh()->status)->toBe(RequestStatus::InProgress)
        ->and(openVisit($this->request)->department_id)->toBe($this->org->ccs->id);
});

// ── Final Quality Check ─────────────────────────────────────────────────────────────────────

test('REQ-16-19 the final check shows the details and route read-only, and is approved or declined with remarks', function () {
    Notification::fake();
    acceptRequest($this->request, $this->registrar);
    passOffice($this->request, $this->registrar);
    passOffice($this->request, User::factory()->departmentStaff($this->org->ccs)->create());
    passOffice($this->request, User::factory()->departmentStaff($this->org->vpf)->create());
    passOffice($this->request, User::factory()->departmentStaff($this->org->chancellor)->create());

    // Brought back by the Registrar: received at once, no Accept receipt (REQ-32-09).
    expect($this->request->fresh()->status)->toBe(RequestStatus::ForFinalApproval);
    stagePage()->assertDontSee('Accept receipt');

    // No checklist: the details and the route, with no Reject/Confirm and no part to confirm.
    stagePage()
        ->assertSeeHtmlInOrder(['data-final-check-details', 'Credential Document', $this->request->credentialType->name, $this->request->requester_name, $this->request->purpose])
        ->assertSeeHtmlInOrder(['data-final-check-route', 'Signatures and Stamps', 'College of Computer Studies', 'Office of the Chancellor'])
        ->assertDontSeeHtml('data-check=')
        ->assertDontSeeHtml('data-confirm-button')
        ->assertDontSee('The credential is complete and accurate.')
        ->assertDontSee('Every office on the route has signed or stamped it.')
        ->assertDontSee('Confirm every part to approve.')
        ->assertDontSee('Return to requester')
        ->call('check', 'credential_document', 'confirmed')->assertStatus(422);

    stagePage()->set('remarks', 'Checked against the records.')->call('approve')->assertSet('blockedParts', [])->assertHasNoErrors();
    expect($this->request->fresh()->status)->toBe(RequestStatus::ReadyForRelease);

    $declined = atFinalCheck(submitRequest($this->owner, $this->org->tor));
    $visit = openVisit($declined);
    stagePage($declined)->call('decline')->assertHasErrors('remarks');
    stagePage($declined)->set('remarks', 'Wrong program printed; requester not a graduate.')->call('decline')->assertHasNoErrors();

    expect($declined->fresh()->status)->toBe(RequestStatus::Declined)
        ->and($visit->fresh()->status->isOpen())->toBeFalse();
    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::Declined);
});

// ── Release ─────────────────────────────────────────────────────────────────────────────────

test('REQ-16-20 release records who claimed it; a representative must be verified, also when declared later', function () {
    $ready = atFinalCheck($this->request);
    workflow()->approveFinalCheck($ready, $this->registrar);
    $representative = declareRepresentative($ready, $this->owner, ['full_name' => 'Rosa Reyes']);

    stagePage()->assertSee('Mark released')->assertDontSee('ID presented')->assertSee('Rosa Reyes')
        ->set('recipientType', 'representative')->set('recipientRepresentativeId', $representative->id)->call('release')
        ->assertDispatched('notify', message: "The representative's SPA and IDs must be verified before the credential is released to them.");

    stagePage()->call('verifyRepresentative', $representative->id);
    stagePage()->set('recipientType', 'representative')->set('recipientRepresentativeId', $representative->id)->call('release')->assertHasNoErrors();

    expect($ready->fresh()->status)->toBe(RequestStatus::Released)
        ->and($ready->release)->recipient_type->toBe(RecipientType::Representative)->recipient_name->toBe('Rosa Reyes')
        ->released_by->toBe($this->registrar->id)->id_presented->toBeNull();

    $owners = atFinalCheck(submitRequest($this->owner, $this->org->tor));
    workflow()->approveFinalCheck($owners, $this->registrar);
    workflow()->release($owners, $this->registrar, RecipientType::Owner);
    expect($owners->fresh()->release->recipient_name)->toBe($owners->requester_name);
});

// ── Removed registrar actions ───────────────────────────────────────────────────────────────

test('REQ-16-21 the registrar cannot change the college, cancel, bypass, pause or record delays', function () {
    acceptRequest($this->request, $this->registrar);
    passOffice($this->request, $this->registrar);

    stagePage()->assertDontSee('Cancel request')->assertDontSee('Bypass')->assertDontSee('Record delay reason')
        ->assertDontSee('Return for customer action')->assertDontSee('Update college');

    expect($this->registrar->can('cancel', $this->request))->toBeFalse()
        ->and(fn () => workflow()->cancel($this->request, $this->registrar, 'No.'))->toThrow(InvalidTransition::class);

    foreach (['changeCollege', 'bypassStep', 'requestCustomerAction', 'customerResponded', 'requestCompliance', 'resolveDeficiency'] as $removed) {
        expect(method_exists(workflow(), $removed))->toBeFalse();
    }

    // The requester still cancels before acceptance.
    $mine = submitRequest($this->owner, $this->org->free);
    workflow()->cancel($mine, $this->owner);
    expect($mine->fresh()->status)->toBe(RequestStatus::Cancelled);
});

// ── Checklist record ────────────────────────────────────────────────────────────────────────

test('REQ-16-22 each part\'s result is kept with the staff member and time, and decisions are in the ledger', function () {
    underEvaluation($this->request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);

    $check = RequestCheck::firstOrFail();
    expect($check)->item->toBe(CheckItem::AcademicRecord)->result->toBe(CheckResult::Rejected)
        ->checked_by->toBe($this->registrar->id)->checked_at->not->toBeNull();

    workflow()->returnForClarification($this->request, $this->registrar, 'Bring the original Form 137.');

    $event = $this->request->events()->get()->last();
    expect($event)->actor_id->toBe($this->registrar->id)
        ->remarks->toBe("Rejected: Academic Record Verification\nBring the original Form 137.");
});
