<?php

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Livewire\Department\Queue as OfficeQueue;
use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Livewire\Registrar\Queue;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| The Registrar carries every paper between offices (spec 32).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
});

function registrarOutgoing(array $query = []): Testable
{
    return Livewire::withQueryParams($query)->actingAs(test()->registrar)->test(Queue::class, ['queue' => 'outgoing']);
}

/** A TOR the Dean received and approved: done, still at the Dean's office. */
function approvedByDean(): CredentialRequest
{
    $request = acceptRequest(submitRequest(test()->owner, test()->org->tor), test()->registrar);
    passOffice($request, test()->registrar);
    workflow()->acceptReceipt(openVisit($request), test()->dean);
    workflow()->completeVisit(openVisit($request), test()->dean);

    return $request->fresh();
}

/** A TOR at VP Finance, received there. */
function receivedAtVpf(): CredentialRequest
{
    $request = approvedByDean();
    workflow()->forwardDocument(openVisit($request), test()->registrar);
    workflow()->acceptReceipt(openVisit($request), test()->vpf);

    return $request->fresh();
}

test('REQ-32-01 an approved document stays in the office, in its Outgoing list and the registrar\'s', function () {
    $request = approvedByDean();

    expect(openVisit($request))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::Done);

    Livewire::actingAs($this->dean)->test(OfficeQueue::class, ['queue' => 'outgoing'])
        ->assertSeeInOrder([$request->reference_no, 'Office of the VP for Finance']);
    registrarOutgoing()->assertSeeInOrder([$request->reference_no, 'College of Computer Studies', 'Forward to Office of the VP for Finance']);
});

test('REQ-32-02 Outgoing is in the registrar\'s sidebar after In Progress and lists every office\'s finished papers, filterable by office', function () {
    $atDean = approvedByDean();
    $atRegistrar = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);   // the Registrar's own task
    $notDone = receivedAtVpf();

    $html = $this->actingAs($this->registrar)->get(route('registrar.outgoing'))->assertOk()->getContent();
    expect($html)->toMatch('#registrar/in-progress".*?registrar/outgoing".*?registrar/final-check"#s');

    registrarOutgoing()
        ->assertSee($atDean->reference_no)->assertSee($atRegistrar->reference_no)->assertDontSee($notDone->reference_no)
        ->assertSee(route('records.show', $atDean), false);
    registrarOutgoing(['office' => (string) $this->org->ccs->id])->assertSee($atDean->reference_no)->assertDontSee($atRegistrar->reference_no);

    $this->actingAs($this->dean)->get(route('registrar.outgoing'))->assertForbidden();
});

test('REQ-32-03 the registrar forwards a row, which the next office still has to receive, and is told of it', function () {
    Notification::fake();
    $request = approvedByDean();

    registrarOutgoing()->call('forward', $request->id)->assertDispatched('notify');

    $visit = openVisit($request);
    expect($visit)->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit)
        ->forwarded_by->toBe($this->registrar->id)->from_department_id->toBe($this->org->ccs->id)
        ->and($request->visits()->where('department_id', $this->org->ccs->id)->sole())->status->toBe(VisitStatus::Completed);
    Notification::assertSentTo($this->vpf, RequestActivity::class);

    Livewire::actingAs($this->vpf)->test(OfficeQueue::class, ['queue' => 'incoming'])->assertSee($request->reference_no);
    registrarOutgoing()->assertDontSee($request->reference_no);
});

test('REQ-32-03 the registrar forwards the ticked papers at once, skipping those no longer ready', function () {
    $a = approvedByDean();
    $b = approvedByDean();
    $notDone = receivedAtVpf();

    $page = registrarOutgoing()->assertSeeHtml('data-select-page')->assertSeeHtml('data-select-row');
    expect($page->html())->toMatch('#<button(?=[^>]*\sdisabled)(?=[^>]*data-forward-selected)[^>]*>#');

    $page->set('selected', [(string) $a->id, (string) $b->id, (string) $notDone->id])
        ->assertSee('Forward (3)')
        ->call('forwardSelected')
        ->assertDispatched('notify', message: 'Forwarded 2 documents. 1 not forwarded: already forwarded, or not ready.')
        ->assertSet('selected', []);

    expect(openVisit($a)->department_id)->toBe($this->org->vpf->id)
        ->and(openVisit($b)->department_id)->toBe($this->org->vpf->id)
        ->and(openVisit($notDone)->status)->toBe(VisitStatus::Received);

    registrarOutgoing()->call('forwardSelected')->assertHasErrors(['selected']);
});

test('REQ-32-04 department staff cannot forward; nor can anyone outside Outgoing', function () {
    $request = approvedByDean();
    $visit = openVisit($request);

    expect($this->dean->can('forward', $visit))->toBeFalse()
        ->and($this->vpf->can('forward', $visit))->toBeFalse()
        ->and($this->registrar->can('forward', $visit))->toBeTrue();

    Livewire::actingAs($this->dean)->test(OfficeQueue::class, ['queue' => 'outgoing'])
        ->assertDontSeeHtml('data-forward')->assertDontSee('Forward to');
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'in_progress'])
        ->call('forward', $request->id)->assertNotFound();
    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'in_progress'])
        ->call('forwardSelected')->assertNotFound();

    expect(openVisit($request)->status)->toBe(VisitStatus::Done);
});

test('REQ-32-05 the department\'s Outgoing list is view-only and replaces To Forward', function () {
    $request = approvedByDean();

    Livewire::actingAs($this->dean)->test(OfficeQueue::class, ['queue' => 'outgoing'])
        ->assertSee('Outgoing')
        ->assertSeeInOrder(['Request', 'Requester', 'Next', 'Since'])
        ->assertSeeInOrder([$request->reference_no, 'Office of the VP for Finance'])
        ->assertDontSeeHtml('data-select-row')->assertDontSeeHtml('data-forward-selected');

    $this->actingAs($this->dean)->get('/department/to-forward')->assertRedirect('/department/outgoing');
    $this->actingAs($this->dean)->get('/department/to-forward/'.$request->reference_no)
        ->assertRedirect('/department/outgoing/'.$request->reference_no);
    $this->actingAs($this->dean)->get(route('department.outgoing.show', $request))->assertOk();
});

test('REQ-32-06 In Progress leaves out the papers waiting to be collected and has no Forward', function () {
    $waiting = approvedByDean();
    $moving = receivedAtVpf();

    Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'in_progress'])
        ->assertDontSee($waiting->reference_no)
        ->assertSee($moving->reference_no)
        ->assertDontSeeHtml('data-forward');
});

test('REQ-32-07 a return keeps the document in the returning office, in Outgoing, and leaves its step open', function () {
    Notification::fake();
    $request = receivedAtVpf();
    $visit = openVisit($request);

    Livewire::actingAs($this->vpf)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->set('returnTo', $this->org->ccs->id)->set('returnReason', 'Unsigned')->set('correctiveAction', 'Sign page 2')
        ->call('returnVisit')->assertHasNoErrors();

    expect($visit->fresh())
        ->status->toBe(VisitStatus::Done)
        ->returned_to_department_id->toBe($this->org->ccs->id)
        ->return_reason->toBe('Unsigned')
        ->corrective_action->toBe('Sign page 2')
        ->done_by->toBe($this->vpf->id)
        ->and($visit->fresh()->isPendingReturn())->toBeTrue()
        ->and($request->steps()->where('department_id', $this->org->vpf->id)->sole()->status)->toBe(StepStatus::Current)
        ->and($request->fresh()->status)->toBe(RequestStatus::ReturnedForCorrection)
        ->and($request->events()->where('type', RequestEventType::DocumentReturned)->exists())->toBeTrue();

    Notification::assertNotSentTo($this->dean, RequestActivity::class, fn ($n) => str_contains($n->title, 'returned to your office'));

    Livewire::actingAs($this->vpf)->test(OfficeQueue::class, ['queue' => 'outgoing'])
        ->assertSeeInOrder([$request->reference_no, 'Return to College of Computer Studies']);
    registrarOutgoing()->assertSeeInOrder([$request->reference_no, 'Office of the VP for Finance', 'Return to College of Computer Studies']);
});

test('REQ-32-08 the registrar carries a returned document back; the corrected one comes back the same way', function () {
    Notification::fake();
    $request = receivedAtVpf();
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned', 'Sign page 2');

    registrarOutgoing()->call('forward', $request->id)->assertDispatched('notify');

    $returned = $request->visits()->where('department_id', $this->org->vpf->id)->sole();
    expect($returned)->status->toBe(VisitStatus::Returned)->completed_by->toBe($this->registrar->id)
        ->and(openVisit($request))->purpose->toBe(VisitPurpose::Correction)->department_id->toBe($this->org->ccs->id)
        ->status->toBe(VisitStatus::InTransit)->forwarded_by->toBe($this->registrar->id);
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was returned to your office for correction");

    // Corrected at the Dean, then back to VP Finance through Outgoing.
    workflow()->acceptReceipt(openVisit($request), $this->dean);
    workflow()->completeVisit(openVisit($request), $this->dean);
    registrarOutgoing()->assertSee('Forward to Office of the VP for Finance')->call('forward', $request->id);

    expect(openVisit($request))->purpose->toBe(VisitPurpose::Resubmission)->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit);
});
