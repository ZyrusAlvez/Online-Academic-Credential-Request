<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Requests\Show as RegistrarShow;
use App\Livewire\Student\Requests\Edit;
use App\Livewire\Student\Requests\Show as RequesterShow;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

/** Sent to the office for the given required items. */
function sentToOffice(CredentialRequest $request, array $items = ['PSA-authenticated birth certificate'], string $remarks = 'Bring the original.'): CredentialRequest
{
    evaluating($request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);
    workflow()->requireOfficeVisit($request, test()->registrar, $items, $remarks);

    return $request->fresh();
}

// ── The decision ────────────────────────────────────────────────────────────────────────────

test('REQ-20-02 Initial Evaluation has four buttons, Approve on the right; remarks are needed to return or decline', function () {
    evaluating($this->request, [CheckItem::Identity->value => CheckResult::Rejected]);

    decisionPage()->assertSeeInOrder(['Remarks', 'Decline', 'Require Office Visit', 'Return for Clarification', 'Approve']);
    decisionPage()->call('returnRequest')->assertHasErrors('remarks');
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

test('REQ-20-04 Require Office Visit asks what is required, records each item and waits for the requester at the office', function () {
    Notification::fake();
    evaluating($this->request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);

    // The dialog lists the credential's requirements, then Other, never calling them missing.
    $dialog = Str::after(decisionPage()->html(), 'data-office-visit-modal');
    expect($dialog)->toContain('Requirements')->toContain('Completed clearance')->toContain('PSA-authenticated birth certificate')
        ->toContain('data-other-checkbox')
        ->and(Str::lower($dialog))->not->toContain('missing requirement');

    decisionPage()->set('remarks', 'Bring them.')->call('requireOfficeVisit')
        ->assertHasErrors('requiredItems')->assertSee('Tick at least one requirement, or Other.');
    decisionPage()->set('requiredItems', ['Not a requirement'])->set('remarks', 'Bring them.')->call('requireOfficeVisit')->assertHasErrors('requiredItems.0');
    expect($this->request->fresh()->status)->toBe(RequestStatus::UnderEvaluation);

    decisionPage()
        ->set('requiredItems', ['PSA-authenticated birth certificate', RegistrarShow::OTHER_ITEM])
        ->set('otherRequirement', 'Original Form 137')
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
        ->remarks->toBe("Required: PSA-authenticated birth certificate; Original Form 137\nBring the originals, personally or through a representative.");

    // The request record heads them as what the office visit requires.
    $this->actingAs($this->registrar)->get(route('records.show', $request))
        ->assertSee('Office Visit Requirements')->assertDontSee('Missing Requirements');

    Notification::assertSentTo($this->owner, RequestActivity::class, fn ($n) => $n->event->type === RequestEventType::ComplianceRequested);

    expect(fn () => workflow()->requireOfficeVisit(submitRequest($this->owner, $this->org->tor), $this->registrar, ['Form 137'], 'Bring it.'))
        ->toThrow(InvalidTransition::class);
});

test('REQ-20-04 ticking Other shows its field, which is then required; remarks are optional', function () {
    evaluating($this->request, [CheckItem::AcademicRecord->value => CheckResult::Rejected]);

    // Other's field is hidden until Other is ticked; the remarks are marked Optional.
    $dialog = Str::after(decisionPage()->html(), 'data-office-visit-modal');
    expect($dialog)->toMatch('#<div[^>]*x-show="[^"]*requiredItems[^"]*"[^>]*style="display: none"[^>]*data-other-field#')
        ->and(Str::after($dialog, 'data-other-field'))->toContain('Please specify')
        ->and(Str::between($dialog, 'data-other-field', 'data-office-visit-remarks'))->toContain('Optional');
    expect(Str::after(decisionPage()->set('requiredItems', [RegistrarShow::OTHER_ITEM])->html(), 'data-office-visit-modal'))
        ->not->toMatch('#style="display: none"[^>]*data-other-field#');

    // Other ticked with nothing written.
    decisionPage()->set('requiredItems', [RegistrarShow::OTHER_ITEM])->call('requireOfficeVisit')
        ->assertHasErrors('otherRequirement')->assertSee('Write what else is required under Other.');

    // Text left under Other counts only while Other is ticked; no remarks are needed.
    decisionPage()
        ->set('requiredItems', ['Completed clearance'])
        ->set('otherRequirement', 'Ignored, Other is not ticked')
        ->call('requireOfficeVisit')
        ->assertHasNoErrors();

    $request = $this->request->fresh();
    expect($request->status)->toBe(RequestStatus::PendingCompliance)
        ->and($request->deficiencies->pluck('description')->all())->toBe(['Completed clearance'])
        ->and($request->events()->get()->last()->remarks)->toBe('Required: Completed clearance');

    // The requester sees the item, and no empty remarks.
    requesterPage()->assertSee('Completed clearance')->assertDontSee('Remarks:');
});

// ── Returned for clarification ─────────────────────────────────────────────────────────────

test('REQ-20-05 the requester sees what was rejected and Edit Request; only they, only while returned', function () {
    returnedForClarification($this->request, [CheckItem::Identity], 'The ID photo is blurred.');

    requesterPage()
        ->assertSee('Please Correct and Clarify Your Request')
        ->assertDontSee('Please Correct Your Request')
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

test('REQ-20-07 resubmitting saves everything and sends the request back, rechecking what changed', function () {
    Notification::fake();
    returnedForClarification($this->request, [CheckItem::Identity], 'The ID photo is blurred.');

    editPage()
        ->set('form.purpose', 'Further studies')
        ->set('profile.contact_number', '09998887777')
        ->set('profile.id_type', 'UMID')
        ->set('profile.id_file', UploadedFile::fake()->image('umid.jpg'))
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
        // Identity was rejected, and the credential request (its purpose) and the contact number
        // changed: only the unchanged academic record stays confirmed.
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

// ── Office visit ────────────────────────────────────────────────────────────────────────────

test('REQ-20-08 the requester is told to visit the office, with what to bring, and cannot resubmit online', function () {
    sentToOffice($this->request, ['PSA-authenticated birth certificate', 'Completed clearance'], 'Bring the originals.');

    requesterPage()
        ->assertSee('Visit the Office of the Registrar')
        ->assertSeeInOrder(['PSA-authenticated birth certificate', 'Completed clearance'])
        ->assertSee('Bring the originals.')
        // No representative was declared on New Request, so none is offered (REQ-11-01, 05).
        ->assertSee('Come in person with a valid ID.')
        ->assertDontSee('Special Power of Attorney')
        ->assertDontSeeHtml('data-edit-request')
        ->assertDontSee('Add representative')
        ->assertSee('Cancel request');

    $this->actingAs($this->owner)->get(route('student.requests.edit', $this->request))->assertForbidden();
    expect(fn () => workflow()->resubmit($this->request, $this->owner))->toThrow(InvalidTransition::class);
});

test('REQ-20-09 the registrar marks each item received, then confirms compliance and the request goes on to payment', function () {
    sentToOffice($this->request, ['PSA-authenticated birth certificate', 'Completed clearance'], 'Bring the originals.');
    [$psa, $clearance] = $this->request->deficiencies()->get()->all();
    $confirmButton = function (): string {
        preg_match('/<button[^>]*data-confirm-compliance[^>]*>/', decisionPage()->html(), $match);

        return $match[0] ?? '';
    };

    // The remarks once, without the items again: they are listed below with Mark Received.
    $panel = Str::of(decisionPage()->html())->after('data-compliance-panel')->before('data-decision-buttons')->toString();
    expect(Str::of($panel)->after('data-visit-remarks')->before('</p>')->squish()->toString())->toBe('><span class="font-semibold">Remarks:</span> Bring the originals.')
        ->and(substr_count($panel, 'PSA-authenticated birth certificate'))->toBe(1)
        ->and($panel)->not->toContain('Required:')->not->toContain('Missing');

    // Confirm Compliance is disabled until every item is received, and the server refuses it too.
    expect($confirmButton())->toMatch('/\sdisabled[\s=>]/');
    decisionPage()->call('confirmCompliance')
        ->assertDispatched('modal-show', name: 'decision-blocked')
        ->assertSee("Can't confirm compliance yet")
        ->assertSet('blockedParts', [['label' => 'PSA-authenticated birth certificate', 'state' => 'to_receive'], ['label' => 'Completed clearance', 'state' => 'to_receive']]);
    expect($this->request->fresh()->status)->toBe(RequestStatus::PendingCompliance);

    decisionPage()->call('markReceived', $psa->id)->assertDispatched('notify', message: 'Marked received.');
    expect($psa->fresh())->resolved_at->not->toBeNull()->resolved_by->toBe($this->registrar->id)
        ->and($confirmButton())->toMatch('/\sdisabled[\s=>]/')
        ->and(fn () => workflow()->confirmCompliance($this->request, $this->registrar))->toThrow(InvalidTransition::class, 'Mark every requirement received first.');

    decisionPage()->call('markReceived', $clearance->id);
    expect($confirmButton())->not->toBeEmpty()->not->toMatch('/\sdisabled[\s=>]/');
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
