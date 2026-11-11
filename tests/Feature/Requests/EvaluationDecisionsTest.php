<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\DocumentKind;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Index as RecordsIndex;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Livewire\Student\Requests\Edit;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Reports\ComplianceReport;
use App\Reports\EvaluatorReport;
use App\Reports\ReportFilter;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Spec 20: Initial Evaluation ends in Approve, Return for Clarification (the requester edits and
| resubmits online), Require Office Visit (the registrar confirms compliance at the office) or
| Decline.
*/

beforeEach(function () {
    Storage::fake(RequestWorkflow::proofDisk());
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->request = submitRequest($this->owner, $this->org->tor);
});

function decisionPage(?CredentialRequest $request = null, ?User $as = null): Testable
{
    return Livewire::actingAs($as ?? test()->registrar)->test(RegistrarShow::class, ['credentialRequest' => ($request ?? test()->request)->fresh()]);
}

function requesterPage(?CredentialRequest $request = null): Testable
{
    return Livewire::actingAs(test()->owner)->test(RequesterShow::class, ['credentialRequest' => ($request ?? test()->request)->fresh()]);
}

function editPage(?CredentialRequest $request = null): Testable
{
    return Livewire::actingAs(test()->owner)->test(Edit::class, ['credentialRequest' => ($request ?? test()->request)->fresh()]);
}

/**
 * Take the request up for evaluation and give each listed part its result.
 *
 * @param  array<string, CheckResult>  $results
 */
function evaluating(CredentialRequest $request, array $results = []): CredentialRequest
{
    workflow()->startEvaluation($request, test()->registrar);

    foreach ($results as $item => $result) {
        workflow()->recordCheck($request, test()->registrar, CheckItem::from($item), $result);
    }

    return $request->fresh();
}

/** Returned for clarification with the given parts rejected and the others confirmed. */
function returnedForClarification(CredentialRequest $request, array $rejected, string $remarks = 'Please correct it.'): CredentialRequest
{
    evaluating($request);

    foreach (CheckItem::for(CheckStage::Evaluation, $request->fresh()) as $item) {
        workflow()->recordCheck($request, test()->registrar, $item, in_array($item, $rejected, true) ? CheckResult::Rejected : CheckResult::Confirmed);
    }

    workflow()->returnForClarification($request, test()->registrar, $remarks);

    return $request->fresh();
}

/** Sent to the office for the given missing items. */
function sentToOffice(CredentialRequest $request, array $missing = ['PSA-authenticated birth certificate'], string $remarks = 'Bring the original.'): CredentialRequest
{
    evaluating($request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);
    workflow()->requireOfficeVisit($request, test()->registrar, $missing, $remarks);

    return $request->fresh();
}

// ── Statuses ────────────────────────────────────────────────────────────────────────────────

test('REQ-20-01 returned for clarification is a status of its own, third in the list and in the table', function () {
    expect(RequestStatus::cases()[2])->toBe(RequestStatus::ReturnedForClarification)
        ->and(RequestStatus::ReturnedForClarification->label())->toBe('Returned for clarification')
        ->and(RequestStatus::ReturnedForClarification->isBeforeAcceptance())->toBeTrue();

    $row = DB::table('request_statuses')->where('value', 'returned_for_clarification')->first();
    expect((int) $row->sort_order)->toBe(3)
        ->and(DB::table('request_statuses')->where('value', 'pending_compliance')->value('meaning'))
        ->toContain('at the Office of the Registrar')
        ->and(DB::table('request_statuses')->where('value', 'returned_for_customer_action')->exists())->toBeTrue();
});

// ── The decision ────────────────────────────────────────────────────────────────────────────

test('REQ-20-02 Initial Evaluation has four buttons, Approve on the right; remarks are needed except to approve', function () {
    evaluating($this->request, [CheckItem::Identity->value => CheckResult::Rejected]);

    decisionPage()->assertSeeInOrder(['Remarks', 'Decline', 'Require Office Visit', 'Return for Clarification', 'Approve']);
    decisionPage()->call('returnRequest')->assertHasErrors('remarks');
    decisionPage()->set('missing', ['PSA-authenticated birth certificate'])->call('requireOfficeVisit')->assertHasErrors('remarks');
    decisionPage()->call('decline')->assertHasErrors('remarks');

    $free = submitRequest($this->owner, $this->org->free);
    evaluating($free);
    checkAll($free, $this->registrar, CheckStage::Evaluation);
    decisionPage($free)->call('approve')->assertHasNoErrors();

    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation)
        ->and($free->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing);
});

test('REQ-20-03 Return for Clarification needs a rejected part, then sends the request back to be corrected', function () {
    Notification::fake();
    declareRepresentative($this->request, $this->owner, ['full_name' => 'Rosa Reyes']);
    evaluating($this->request);
    checkAll($this->request, $this->registrar, CheckStage::Evaluation);

    decisionPage()->set('remarks', 'Fix it.')->call('returnRequest')
        ->assertDispatched('modal-show', name: 'decision-blocked')
        ->assertSet('blockers', ['Reject at least one part of the checklist, so the requester knows what to correct.']);

    workflow()->recordCheck($this->request, $this->registrar, CheckItem::Identity, CheckResult::Rejected);
    workflow()->recordCheck($this->request, $this->registrar, CheckItem::Representative, CheckResult::Rejected);
    decisionPage()->set('remarks', 'The ID photo is blurred; the SPA is unsigned.')->call('returnRequest')->assertHasNoErrors();

    $request = $this->request->fresh();
    expect($request)
        ->status->toBe(RequestStatus::ReturnedForClarification)
        ->identity_status->toBe(ReviewStatus::Rejected)
        ->identity_remarks->toBe('The ID photo is blurred; the SPA is unsigned.')
        ->and($request->representatives()->first()->status)->toBe(ReviewStatus::Rejected)
        ->and($request->events()->get()->last())
        ->type->toBe(RequestEventType::CustomerActionRequested)
        ->remarks->toBe("Rejected: User Identity Verification; Representative Verification\nThe ID photo is blurred; the SPA is unsigned.");

    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::CustomerActionRequested);

    // The lock has ended: another registrar sees the request, not "under evaluation by".
    decisionPage(as: User::factory()->registrar()->create())
        ->assertDontSee('is under evaluation by')->assertSee('Returned for Clarification')->assertSee('The ID photo is blurred');
});

test('REQ-20-04 Require Office Visit asks what is missing, records each item and waits for the requester at the office', function () {
    Notification::fake();
    evaluating($this->request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);

    // The dialog lists the credential's requirements.
    decisionPage()->assertSeeHtml('data-office-visit-modal')->assertSee('Completed clearance')->assertSee('PSA-authenticated birth certificate');

    decisionPage()->set('remarks', 'Bring them.')->call('requireOfficeVisit')->assertHasErrors('otherMissing');
    decisionPage()->set('missing', ['Not a requirement'])->set('remarks', 'Bring them.')->call('requireOfficeVisit')->assertHasErrors('missing.0');
    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);

    decisionPage()
        ->set('missing', ['PSA-authenticated birth certificate'])
        ->set('otherMissing', 'Original Form 137')
        ->set('remarks', 'Bring the originals, personally or through a representative.')
        ->call('requireOfficeVisit')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'office-visit');

    $request = $this->request->fresh();
    expect($request->status)->toBe(RequestStatus::PendingCompliance)
        ->and($request->deficiencies->pluck('description')->all())->toBe(['PSA-authenticated birth certificate', 'Original Form 137'])
        ->and($request->deficiencies->pluck('recorded_by')->unique()->all())->toBe([$this->registrar->id])
        ->and($request->events()->get()->last())
        ->type->toBe(RequestEventType::ComplianceRequested)
        ->remarks->toBe("Missing: PSA-authenticated birth certificate; Original Form 137\nBring the originals, personally or through a representative.");

    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::ComplianceRequested);

    expect(fn () => workflow()->requireOfficeVisit(submitRequest($this->owner, $this->org->tor), $this->registrar, ['Form 137'], 'Bring it.'))
        ->toThrow(InvalidTransition::class);
});

// ── Returned for clarification ─────────────────────────────────────────────────────────────

test('REQ-20-05 the requester sees what was rejected and Edit Request; only they, only while returned', function () {
    returnedForClarification($this->request, [CheckItem::Identity], 'The ID photo is blurred.');

    requesterPage()
        ->assertSee('Please Correct Your Request')
        ->assertSee('User Identity Verification')
        ->assertSee('The ID photo is blurred.')
        ->assertSeeHtml('href="'.route('student.requests.edit', $this->request).'"')
        ->assertDontSee('Visit the Office of the Registrar');

    $this->actingAs($this->owner)->get(route('student.requests.edit', $this->request))->assertOk()
        ->assertSee('<title>Edit Request '.$this->request->reference_no, false);
    $this->actingAs(requester($this->org->ccs))->get(route('student.requests.edit', $this->request))->assertNotFound();
    $this->actingAs($this->registrar)->get(route('student.requests.edit', $this->request))->assertForbidden();

    $submitted = submitRequest($this->owner, $this->org->tor);
    $this->actingAs($this->owner)->get(route('student.requests.edit', $submitted))->assertForbidden();
    requesterPage($submitted)->assertDontSeeHtml('data-edit-request');
});

test('REQ-20-06 Edit Request is the New Request form filled in from the request, ending in Resubmit Request', function () {
    $this->request->forceFill(['purpose' => 'For my own records'])->save();
    returnedForClarification($this->request, [CheckItem::CredentialRequest]);

    editPage()
        ->assertSet('form.credential_type_id', $this->org->tor->id)
        ->assertSet('form.purpose', CredentialRequestForm::OTHER_PURPOSE)
        ->assertSet('form.purpose_other', 'For my own records')
        ->assertSet('profile.first_name', $this->owner->first_name)
        ->assertSee('Edit request '.$this->request->reference_no)
        ->assertDontSee('6. Who Will Transact?')
        ->assertDontSeeHtml('data-id-rejected')
        ->call('review')
        ->assertHasNoErrors()
        ->assertSee('Summary')
        ->assertSeeHtml('data-resubmit-note')
        ->assertSee('Resubmit Request');
});

test('REQ-15-17 the Edit Request summary has sections 1 to 5, Summary as its title, and only new files', function () {
    returnedForClarification($this->request, [CheckItem::CredentialRequest]);

    $html = editPage()->call('review')->assertHasNoErrors()->html();

    expect($html)->toContain('>1. What Do You Need?</h2>')->toContain('>5. Valid ID</h2>')
        ->not->toContain('6. Who Will Transact?')
        ->not->toContain('Edit request '.$this->request->reference_no)
        ->toContain('>Summary</h1>')
        ->toContain('No new files');
});

test('REQ-20-06 a rejected ID requires another; its reason is on the request page, not repeated on Edit Request', function () {
    returnedForClarification($this->request, [CheckItem::Identity], 'The ID photo is blurred.');

    editPage()
        ->assertDontSeeHtml('data-id-rejected')
        ->assertDontSee('Your valid ID was not accepted')
        ->assertDontSee('The ID photo is blurred.')
        ->assertDontSeeHtml('data-saved-id')
        ->assertSeeHtml('wire:model="profile.id_file"')
        ->call('review')
        ->assertHasErrors('profile.id_file');

    requesterPage($this->request)->assertSee('The ID photo is blurred.');
});

test('REQ-15-18 Edit Request shows the saved ID with its Remove button and no ID fields while the ID stands', function () {
    returnedForClarification($this->request, [CheckItem::CredentialRequest]);
    $sent = $this->request->identityDocument;

    editPage()
        ->assertSeeHtml('data-saved-id')
        ->assertSeeHtml('data-remove-saved-id')
        ->assertDontSeeHtml('wire:model="profile.id_file"')
        ->call('removeSavedId')
        ->assertSeeHtml('wire:model="profile.id_file"');

    // The request keeps the ID it was sent with (REQ-15-08).
    expect($this->request->fresh()->identity_document_id)->toBe($sent->id)
        ->and($sent->fresh()->removed_at)->not->toBeNull();
});

test('REQ-20-07 resubmitting saves everything and sends the request back, rechecking what changed', function () {
    Notification::fake();
    returnedForClarification($this->request, [CheckItem::Identity], 'The ID photo is blurred.');

    editPage()
        ->set('form.purpose', 'Further studies')
        ->set('profile.contact_number', '09998887777')
        ->set('profile.id_type', 'UMID')
        ->set('profile.id_file', UploadedFile::fake()->image('umid.jpg'))
        ->set('requirementFiles.'.$this->org->tor->id.'.0', [UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf')])
        ->call('review')
        ->assertHasNoErrors()
        ->set('profile.privacy_consent', true)
        ->set('note', 'Clearer photo of my UMID.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.requests.show', $this->request));

    $request = $this->request->fresh();
    $newId = $this->owner->latestIdentityDocument()->firstOrFail();
    expect($request)
        ->status->toBe(RequestStatus::Submitted)
        ->purpose->toBe('Further studies')
        ->contact_number->toBe('09998887777')
        ->identity_document_id->toBe($newId->id)
        ->identity_status->toBe(ReviewStatus::Pending)
        ->and($newId->id_type)->toBe('UMID')
        ->and($this->owner->fresh()->contact_number)->toBe('09998887777')
        ->and($request->documents()->where('kind', DocumentKind::Requirement)->pluck('requirement')->all())->toBe(['Completed clearance'])
        // Identity was rejected, the credential request and contact number changed, new copies came:
        // only the unchanged academic record stays confirmed.
        ->and($request->checks()->pluck('item')->map->value->all())->toBe([CheckItem::AcademicRecord->value])
        ->and($request->events()->where('type', RequestEventType::Resubmitted)->value('remarks'))->toBe('Clearer photo of my UMID.');

    Storage::disk(RequestWorkflow::proofDisk())->assertExists($newId->path);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::Resubmitted);
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'evaluation'])->assertSee($request->reference_no);
});

test('REQ-20-07 unchanged parts stay confirmed; a new credential brings its own fee', function () {
    returnedForClarification($this->request, [CheckItem::CredentialRequest]);

    workflow()->resubmit($this->request, $this->owner, null, ['credential_type_id' => $this->org->free->id, 'purpose' => 'Employment']);

    $request = $this->request->fresh();
    expect($request)
        ->credential_type_id->toBe($this->org->free->id)
        ->requires_payment->toBeFalse()
        ->amount_due->toBeNull()
        ->and($request->checks()->pluck('item')->map->value->sort()->values()->all())->toBe([CheckItem::AcademicRecord->value, CheckItem::Identity->value])
        ->and($request->events()->where('type', RequestEventType::CredentialChanged)->value('remarks'))
        ->toBe('Transcript of Records → Certification of Enrollment');

    // All or nothing: someone else's ID is refused and nothing changes.
    $other = returnedForClarification(submitRequest($this->owner, $this->org->tor), [CheckItem::Identity]);
    $intruder = requester($this->org->ccs);
    expect(fn () => workflow()->resubmit($other, $this->owner, identity: $intruder->latestIdentityDocument()->firstOrFail()))->toThrow(InvalidTransition::class)
        ->and(fn () => workflow()->resubmit($other, $this->owner))->toThrow(InvalidTransition::class, 'Upload another valid ID')
        ->and($other->fresh()->status)->toBe(RequestStatus::ReturnedForClarification);
});

test('REQ-20-07 new requirement copies have the credential request checked again', function () {
    returnedForClarification($this->request, [CheckItem::AcademicRecord]);

    workflow()->resubmit($this->request, $this->owner, newRequirementCopies: true);

    // The rejected academic record and the credential request (its requirements) are checked again.
    expect($this->request->fresh()->checks()->pluck('item')->map->value->all())->toBe([CheckItem::Identity->value]);
});

// ── Office visit ────────────────────────────────────────────────────────────────────────────

test('REQ-20-08 the requester is told to visit the office, with what to bring, and cannot resubmit online', function () {
    sentToOffice($this->request, ['PSA-authenticated birth certificate', 'Completed clearance'], 'Bring the originals.');

    requesterPage()
        ->assertSee('Visit the Office of the Registrar')
        ->assertSeeInOrder(['PSA-authenticated birth certificate', 'Completed clearance'])
        ->assertSee('Bring the originals.')
        ->assertSee('Special Power of Attorney')
        ->assertDontSeeHtml('data-edit-request')
        ->assertSee('Add representative')
        ->assertSee('Cancel request');

    $this->actingAs($this->owner)->get(route('student.requests.edit', $this->request))->assertForbidden();
    expect(fn () => workflow()->resubmit($this->request, $this->owner))->toThrow(InvalidTransition::class);
});

test('REQ-20-09 the registrar marks each item received, then confirms compliance and the request goes on to payment', function () {
    sentToOffice($this->request, ['PSA-authenticated birth certificate', 'Completed clearance']);
    [$psa, $clearance] = $this->request->deficiencies()->get()->all();

    decisionPage()->assertSeeHtml('data-compliance-panel')->assertSee('Mark Received')
        ->call('confirmCompliance')
        ->assertDispatched('modal-show', name: 'decision-blocked')
        ->assertSee("Can't confirm compliance yet")
        ->assertSet('blockedParts', [['label' => 'PSA-authenticated birth certificate', 'state' => 'missing'], ['label' => 'Completed clearance', 'state' => 'missing']]);

    decisionPage()->call('markReceived', $psa->id)->assertDispatched('notify', message: 'Marked received.');
    expect($psa->fresh())->resolved_at->not->toBeNull()->resolved_by->toBe($this->registrar->id)
        ->and(fn () => workflow()->confirmCompliance($this->request, $this->registrar))->toThrow(InvalidTransition::class);

    decisionPage()->call('markReceived', $clearance->id);
    decisionPage()->assertSee('Received')->call('confirmCompliance')->assertHasNoErrors();

    $request = $this->request->fresh();
    expect($request)
        ->status->toBe(RequestStatus::AwaitingPayment)
        ->identity_status->toBe(ReviewStatus::Verified)
        ->requirements_completed_at->not->toBeNull()
        ->and($request->checkResults(CheckStage::Evaluation))->each->toBe(CheckResult::Confirmed)
        ->and($request->events()->where('type', RequestEventType::DeficiencyResolved)->pluck('remarks')->all())
        ->toBe(['PSA-authenticated birth certificate', 'Completed clearance']);

    // A free request is accepted directly; a request can also be declined at the office.
    $free = sentToOffice(submitRequest($this->owner, $this->org->free), ['Original Form 137']);
    workflow()->markReceived($free->deficiencies()->firstOrFail(), $this->registrar);
    workflow()->confirmCompliance($free, $this->registrar);
    expect($free->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing);

    $declined = sentToOffice(submitRequest($this->owner, $this->org->tor));
    decisionPage($declined)->set('remarks', 'The records show no graduation.')->call('decline')->assertHasNoErrors();
    expect($declined->fresh()->status)->toBe(RequestStatus::Declined);
});

// ── Queues, wording and reports ─────────────────────────────────────────────────────────────

test('REQ-20-10 both kinds of return wait for the requester in the queue and in Records', function () {
    $clarify = returnedForClarification(submitRequest($this->owner, $this->org->free), [CheckItem::Identity]);
    $visit = sentToOffice(submitRequest($this->owner, $this->org->tor));

    Livewire::withQueryParams(['waiting' => true])->actingAs($this->registrar)->test(Queue::class, ['queue' => 'evaluation'])
        ->assertSee($clarify->reference_no)->assertSee('Returned for clarification')
        ->assertSee($visit->reference_no)->assertSee('Pending compliance')
        ->assertDontSee($this->request->reference_no);

    Livewire::actingAs($this->registrar)->test(RecordsIndex::class)->set('status', 'waiting_for_requester')
        ->assertSee($clarify->reference_no)->assertSee($visit->reference_no)->assertDontSee($this->request->reference_no);
});

test('REQ-20-11 where the request is, in the requester\'s and in the staff\'s words', function () {
    $clarify = returnedForClarification(submitRequest($this->owner, $this->org->free), [CheckItem::Identity]);
    $visit = sentToOffice(submitRequest($this->owner, $this->org->tor));

    expect($clarify->whereabouts())->toBe('Waiting for you to correct your request')
        ->and($clarify->staffWhereabouts())->toBe('Waiting for the requester to correct the request')
        ->and($visit->whereabouts())->toBe('Waiting for you at the Office of the Registrar')
        ->and($visit->staffWhereabouts())->toBe('Waiting for the requester at the Office of the Registrar');
});

test('REQ-20-12 the reports keep the two returns apart', function () {
    $clarify = returnedForClarification(submitRequest($this->owner, $this->org->free), [CheckItem::Identity], 'The ID photo is blurred.');
    $visit = sentToOffice(submitRequest($this->owner, $this->org->tor), ['Completed clearance'], 'Bring it.');

    $rows = collect((new ComplianceReport)->rows(new ReportFilter))->keyBy(0);
    expect($rows[$clarify->reference_no][4])->toBe('Returned for clarification')
        ->and($rows[$clarify->reference_no][5])->toBe('Rejected: User Identity Verification – The ID photo is blurred.')
        ->and($rows[$visit->reference_no][4])->toBe('Pending compliance')
        ->and($rows[$visit->reference_no][5])->toBe('Missing: Completed clearance');

    // Evaluator, taken up, complete, returned for clarification, sent for compliance, declined…
    expect(collect((new EvaluatorReport)->rows(new ReportFilter))->firstWhere(0, $this->registrar->name))
        ->toMatchArray([0 => $this->registrar->name, 1 => 2, 2 => 0, 3 => 1, 4 => 1, 5 => 0]);
});

test('REQ-20-13 there is no Reopen evaluation and no online resubmit of an office visit; both can be cancelled', function () {
    sentToOffice($this->request);

    decisionPage()->assertDontSee('Reopen evaluation');
    expect(method_exists(workflow(), 'reopenEvaluation'))->toBeFalse()
        ->and(method_exists(workflow(), 'replaceIdentity'))->toBeFalse()
        ->and(method_exists(RegistrarShow::class, 'reopenEvaluation'))->toBeFalse()
        ->and($this->owner->can('edit', $this->request->fresh()))->toBeFalse();

    workflow()->cancel($this->request, $this->owner);
    $clarify = returnedForClarification(submitRequest($this->owner, $this->org->free), [CheckItem::Identity]);
    workflow()->cancel($clarify, $this->owner);

    expect($this->request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($clarify->fresh()->status)->toBe(RequestStatus::Cancelled);
});
