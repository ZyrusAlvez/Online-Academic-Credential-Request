<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Registrar\Requests\Show;
use App\Livewire\Student\Requests\Show as RequesterShow;
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

/** A paid request with its proof of payment awaiting validation. */
function awaitingValidation(CredentialRequest $request): CredentialRequest
{
    evaluate($request, test()->registrar);
    uploadPayment($request);

    return $request->fresh();
}

/** An accepted request taken through every office to the received final quality check. */
function atFinalCheck(CredentialRequest $request): CredentialRequest
{
    $registrar = test()->registrar;
    acceptRequest($request, $registrar);
    passOffice($request, $registrar);                                                       // Registrar prepares
    passOffice($request, User::factory()->departmentStaff(test()->org->ccs)->create());       // Dean
    passOffice($request, User::factory()->departmentStaff(test()->org->vpf)->create());       // VP Finance
    passOffice($request, User::factory()->departmentStaff(test()->org->chancellor)->create()); // Chancellor
    workflow()->acceptReceipt(openVisit($request), $registrar);

    return $request->fresh();
}

// ── Navigation and queues ───────────────────────────────────────────────────────────────────

test('REQ-18-03 the registrar has one page per stage and Records, and no Requests list', function () {
    // REQ-16-01, superseded by REQ-18-03; the sidebar itself is in tests/Feature/Admin/AdminRoleTest.php.
    $this->actingAs($this->registrar)->get('/registrar/requests')->assertNotFound();

    foreach (['registrar.evaluation', 'registrar.payments', 'registrar.in-progress', 'registrar.final-check', 'registrar.release', 'records'] as $route) {
        $this->actingAs($this->registrar)->get(route($route))->assertOk();
        $this->actingAs($this->owner)->get(route($route))->assertForbidden();
    }
});

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

test('REQ-16-03 Payment Validation lists the proofs awaiting verification', function () {
    $paying = awaitingValidation(submitRequest($this->owner, $this->org->tor));

    queuePage('payment')->assertSee($paying->reference_no)->assertSee('₱530.00')->assertDontSee($this->request->reference_no);
});

test('REQ-16-04 In Progress tracks the office holding each document, filterable by office, without actions', function () {
    $atDean = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($atDean, $this->registrar);
    $finalCheck = atFinalCheck(submitRequest($this->owner, $this->org->tor));

    queuePage('in_progress')
        ->assertSee($atDean->reference_no)
        ->assertSee('College of Computer Studies')
        ->assertSee('not yet received')
        ->assertSee(route('records.show', $atDean), false)
        ->assertDontSee($finalCheck->reference_no)
        ->assertDontSee('Approve');

    // Forwarded to the Dean but not yet received: still with the Registrar (REQ-17-18).
    queuePage('in_progress', ['office' => (string) $this->org->vpf->id])->assertDontSee($atDean->reference_no);
    queuePage('in_progress', ['office' => (string) $this->org->ccs->id])->assertDontSee($atDean->reference_no);
    queuePage('in_progress', ['office' => (string) $this->org->registrarOffice->id])->assertSee($atDean->reference_no);
});

test('REQ-16-05 Final Quality Check lists the documents back at the Registrar for the final check', function () {
    $finalCheck = atFinalCheck(submitRequest($this->owner, $this->org->tor));

    queuePage('final_check')->assertSee($finalCheck->reference_no)->assertDontSee($this->request->reference_no);
});

test('REQ-16-06 Release lists the credentials ready for release', function () {
    $ready = atFinalCheck(submitRequest($this->owner, $this->org->tor));
    checkAll($ready, $this->registrar, CheckStage::FinalCheck);
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

test('REQ-16-23 staff see where a request is in the requester\'s terms, the requester in their own', function () {
    $paying = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar)->fresh();
    $returned = underEvaluation(submitRequest($this->owner, $this->org->free), [CheckItem::Identity->value => CheckResult::Rejected]);
    workflow()->returnForClarification($returned, $this->registrar, 'Blurred ID.');

    expect($paying->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($returned->fresh()->status)->toBe(RequestStatus::ReturnedForClarification);

    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)
        ->assertSee("Waiting for the requester's payment")
        ->assertSee('Waiting for the requester to correct the request')
        ->assertDontSee('Waiting for your payment')
        ->assertDontSee('Waiting for you to correct');

    Livewire::actingAs($this->registrar)->test(RecordShow::class, ['credentialRequest' => $paying])
        ->assertSee("Where it is now: Waiting for the requester's payment");

    Livewire::actingAs($this->owner)->test(RequesterShow::class, ['credentialRequest' => $paying])
        ->assertSee('Waiting for your payment')->assertDontSee("the requester's payment");

    expect($paying->staffWhereabouts())->toBe("Waiting for the requester's payment")
        ->and($returned->fresh()->staffWhereabouts())->toBe('Waiting for the requester to correct the request')
        ->and($this->request->staffWhereabouts())->toBe($this->request->whereabouts());
});

test('REQ-16-08 there is no CSV download of requests or reports', function () {
    $this->actingAs($this->registrar)->get('/registrar/requests/export')->assertNotFound();
    $this->actingAs($this->registrar)->get('/registrar/reports/daily/export')->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports'))->assertOk()->assertDontSee('Export CSV');
    $this->actingAs($this->registrar)->get(route('records'))->assertOk()->assertDontSee('Export CSV');
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

// ── Initial Evaluation ──────────────────────────────────────────────────────────────────────

test('REQ-16-10 the evaluator sees a read-only checklist of the requester\'s data with Confirm and Reject', function () {
    underEvaluation($this->request);

    stagePage()
        ->assertSeeInOrder(['Credential Request Verification', 'User Identity Verification', 'Academic Record Verification'])
        ->assertDontSee('Representative Verification')
        ->assertSee($this->request->purpose)
        ->assertSee($this->owner->email)
        ->assertSee('Open '.$this->request->identityDocument->id_type)
        ->assertSee('College of Computer Studies')
        ->assertSee('Confirm')->assertSee('Reject')
        ->assertDontSee('Update college')->assertDontSee('Requirements to check')->assertDontSee('Cancel request');

    stagePage()->call('check', CheckItem::Identity->value, CheckResult::Rejected->value);
    stagePage()->assertSeeHtml('data-check="identity" data-result="rejected"');
    stagePage()->call('check', CheckItem::Identity->value, CheckResult::Confirmed->value);
    stagePage()->assertSeeHtml('data-check="identity" data-result="confirmed"');

    // Through a representative: the fourth part appears.
    $represented = submitRequest($this->owner, $this->org->free);
    declareRepresentative($represented, $this->owner, ['full_name' => 'Rosa Reyes']);
    underEvaluation($represented);
    stagePage($represented)->assertSee('Representative Verification')->assertSee('Rosa Reyes');

    expect(method_exists(Show::class, 'changeCollege'))->toBeFalse();
});

test('REQ-16-24 each checklist part shows its data as summary rows, label left and value right', function () {
    // A label/value row from the sm breakpoint, the same as the requester's summary (REQ-15-17).
    $row = fn (string $label, string $value) => '#<div class="py-3 [^"]*sm:grid sm:grid-cols-\[minmax\(0,2fr\)_minmax\(0,3fr\)\][^"]*" data-summary-row>\s*<dt class="text-zinc-600">'.preg_quote($label, '#').'</dt>\s*<dd[^>]*>\s*'.preg_quote(e($value), '#').'#';

    underEvaluation($this->request);
    expect(stagePage()->html())
        ->toMatch($row('Transacting', 'Personally'))
        ->toMatch($row('Purpose', $this->request->purpose))
        ->toMatch($row('Name', $this->request->requester_name))
        ->toMatch($row('College', 'College of Computer Studies'));

    $paid = awaitingValidation(submitRequest($this->owner, $this->org->tor));
    expect(stagePage($paid)->html())->toMatch($row('Reference', 'REF-123'));

    $final = atFinalCheck(submitRequest($this->owner, $this->org->tor));
    expect(stagePage($final)->html())->toMatch($row('Issued to', $final->requester_name));
});

test('REQ-16-11 remarks are optional to approve and required to return or decline', function () {
    underEvaluation($this->request, [CheckItem::Identity->value => CheckResult::Rejected]);

    // The buttons themselves are REQ-20-02's.
    stagePage()->call('returnRequest')->assertHasErrors('remarks');
    stagePage()->call('requireOfficeVisit')->assertHasErrors('remarks');
    stagePage()->call('decline')->assertHasErrors('remarks');

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

// ── Payment Validation ──────────────────────────────────────────────────────────────────────

test('REQ-16-17 payment validation: approve with both parts and the OR number; return or decline with remarks', function () {
    awaitingValidation($this->request);

    stagePage()->assertSee('Proof of Payment')->assertSee('Amount and Reference')->assertSee('REF-123')->assertSee('Official receipt no.');

    stagePage()->set('officialReceiptNo', 'OR-1')->call('approve')
        ->assertSet('blockedParts', [['label' => 'Proof of Payment', 'state' => 'unchecked'], ['label' => 'Amount and Reference', 'state' => 'unchecked']]);
    checkAll($this->request, $this->registrar, CheckStage::Payment);
    stagePage()->call('approve')->assertHasErrors('officialReceiptNo')->assertSet('blockedParts', [['label' => 'Official receipt number', 'state' => 'missing']])->assertSet('blockers', []);
    stagePage()->set('officialReceiptNo', 'OR-1')->call('approve')->assertHasNoErrors();

    expect($this->request->fresh())->status->toBe(RequestStatus::AcceptedForProcessing)->due_at->not->toBeNull()
        ->and($this->request->paymentProofs()->first())->status->toBe(PaymentStatus::Verified)->official_receipt_no->toBe('OR-1');

    $returned = awaitingValidation(submitRequest($this->owner, $this->org->tor));
    stagePage($returned)->call('returnRequest')->assertHasErrors('remarks');
    stagePage($returned)->set('remarks', 'Amount does not match.')->call('returnRequest')->assertHasNoErrors();
    expect($returned->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($returned->paymentProofs()->first()->status)->toBe(PaymentStatus::Rejected);

    $declined = awaitingValidation(submitRequest($this->owner, $this->org->tor));
    stagePage($declined)->set('remarks', 'No matching payment after three attempts.')->call('decline')->assertHasNoErrors();
    expect($declined->fresh()->status)->toBe(RequestStatus::Declined);
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

test('REQ-16-19 the final check is received first, then approved with both parts or declined with remarks', function () {
    Notification::fake();
    acceptRequest($this->request, $this->registrar);
    passOffice($this->request, $this->registrar);
    passOffice($this->request, User::factory()->departmentStaff($this->org->ccs)->create());
    passOffice($this->request, User::factory()->departmentStaff($this->org->vpf)->create());
    passOffice($this->request, User::factory()->departmentStaff($this->org->chancellor)->create());

    stagePage()->assertSee('Accept receipt')->assertDontSee('Credential Document')->call('acceptReceipt');
    expect($this->request->fresh()->status)->toBe(RequestStatus::ForFinalApproval);

    stagePage()->assertSee('Credential Document')->assertSee('Signatures and Stamps')->assertDontSee('Return to requester')
        ->call('approve')->assertSet('blockedParts', [['label' => 'Credential Document', 'state' => 'unchecked'], ['label' => 'Signatures and Stamps', 'state' => 'unchecked']]);
    checkAll($this->request, $this->registrar, CheckStage::FinalCheck);
    stagePage()->call('approve');
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
    checkAll($ready, $this->registrar, CheckStage::FinalCheck);
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
    checkAll($owners, $this->registrar, CheckStage::FinalCheck);
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

    stagePage()->set('delayCategory', 'pending_signature')->set('delayReason', 'Late.')->call('recordDelay')->assertForbidden();

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
