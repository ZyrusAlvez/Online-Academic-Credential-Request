<?php

use App\Enums\DelayCategory;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Department\Dashboard;
use App\Livewire\Department\Requests\Show as OfficeShow;
use App\Livewire\Registrar\Requests\Show;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
    $this->chancellor = User::factory()->departmentStaff($this->org->chancellor)->create();
    $this->request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
});

afterEach(fn () => Carbon::setTestNow());

/** Move the TOR to VP Finance: Registrar prepares, Dean signs. */
function toVpFinance(): CredentialRequest
{
    passOffice(test()->request, test()->registrar);
    passOffice(test()->request, test()->dean);

    return test()->request->fresh();
}

test('REQ-05-01 acceptance copies the route with the Dean resolved and the final check appended', function () {
    $steps = $this->request->steps()->with('department')->get();

    expect($steps->pluck('department.code')->all())->toBe(['REG', 'CCS', 'VPF', 'CHAN', 'REG'])
        ->and(openVisit($this->request))->department_id->toBe($this->org->registrarOffice->id)->status->toBe(VisitStatus::InTransit);
});

test('REQ-05-02 the office queue shows incoming, in-office and history', function () {
    passOffice($this->request, $this->registrar);

    Livewire::actingAs($this->dean)->test(Dashboard::class)
        ->assertSee($this->request->reference_no)
        ->assertSee('Incoming (1)')
        ->set('tab', 'in_office')->assertDontSee($this->request->reference_no);

    workflow()->acceptReceipt(openVisit($this->request), $this->dean);

    Livewire::actingAs($this->dean)->test(Dashboard::class)
        ->assertSee('In our office (1)')
        ->set('tab', 'in_office')->assertSee($this->request->reference_no);

    workflow()->completeVisit(openVisit($this->request), $this->dean);

    Livewire::actingAs($this->dean)->test(Dashboard::class)
        ->set('tab', 'history')->assertSee($this->request->reference_no)->assertSee('Completed');
    Livewire::actingAs($this->chancellor)->test(Dashboard::class)->assertDontSee($this->request->reference_no);
});

test('REQ-05-03 receipt is confirmed separately from forwarding', function () {
    $visit = openVisit($this->request);
    expect($visit->received_at)->toBeNull();

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])->call('acceptReceipt');

    expect($visit->fresh())
        ->status->toBe(VisitStatus::Received)
        ->received_by->toBe($this->registrar->id)
        ->received_at->not->toBeNull()
        ->and($this->request->fresh()->status)->toBe(RequestStatus::InProgress);
});

test('REQ-05-04 approving releases the document to the next office, which must receive it', function () {
    passOffice($this->request, $this->registrar);

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $this->request])
        ->call('acceptReceipt')
        ->set('completeRemarks', 'Signed')
        ->call('completeVisit');

    $visits = $this->request->visits()->get();
    expect($visits->pluck('status')->all())->toBe([VisitStatus::Completed, VisitStatus::Completed, VisitStatus::InTransit])
        ->and($visits[1])->completed_by->toBe($this->dean->id)->remarks->toBe('Signed')
        ->and($visits[2])->department_id->toBe($this->org->vpf->id)->from_department_id->toBe($this->org->ccs->id)
        ->and($this->request->steps()->where('status', StepStatus::Completed)->count())->toBe(2);
});

test('REQ-05-05 the final quality check makes the request ready for release', function () {
    $request = toVpFinance();
    passOffice($request, $this->vpf);
    passOffice($request, $this->chancellor);

    workflow()->acceptReceipt(openVisit($request), $this->registrar);
    expect($request->fresh()->status)->toBe(RequestStatus::ForFinalApproval);

    workflow()->completeVisit(openVisit($request), $this->registrar);
    expect($request->fresh())
        ->status->toBe(RequestStatus::ReadyForRelease)
        ->ready_at->not->toBeNull();
});

test('REQ-05-06 an office returns the document to an earlier office with reason and corrective action', function () {
    $request = toVpFinance();
    $visit = openVisit($request);
    workflow()->acceptReceipt($visit, $this->vpf);

    Livewire::actingAs($this->vpf)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->assertSee('Return for correction')
        ->set('returnTo', $this->org->registrarOffice->id)
        ->call('returnVisit')->assertHasErrors(['returnReason', 'correctiveAction'])
        ->set('returnReason', 'Wrong middle name')
        ->set('correctiveAction', 'Reprint the TOR')
        ->call('returnVisit')->assertHasNoErrors();

    $correction = openVisit($request);
    expect($visit->fresh())
        ->status->toBe(VisitStatus::Returned)
        ->returned_to_department_id->toBe($this->org->registrarOffice->id)
        ->return_reason->toBe('Wrong middle name')
        ->and($correction)->purpose->toBe(VisitPurpose::Correction)->department_id->toBe($this->org->registrarOffice->id)
        ->and($request->fresh()->status)->toBe(RequestStatus::ReturnedForCorrection);
});

test('REQ-05-06 a document can only go back to an office it already passed', function () {
    $request = toVpFinance();
    $visit = openVisit($request);
    workflow()->acceptReceipt($visit, $this->vpf);

    expect(fn () => workflow()->returnVisit($visit, $this->vpf, $this->org->chancellor, 'x', 'y'))
        ->toThrow(InvalidTransition::class, 'already passed through');
});

test('REQ-05-07 the corrected document goes back and is received again before the route continues', function () {
    $request = toVpFinance();
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->registrarOffice, 'Wrong name', 'Reprint');

    passOffice($request, $this->registrar);   // correction received and sent back

    $resubmission = openVisit($request);
    expect($resubmission)->purpose->toBe(VisitPurpose::Resubmission)->department_id->toBe($this->org->vpf->id)->visit_no->toBe(2)
        ->and($request->fresh()->status)->toBe(RequestStatus::ReturnedForCorrection);

    workflow()->acceptReceipt($resubmission, $this->vpf);
    expect($request->fresh()->status)->toBe(RequestStatus::InProgress);

    workflow()->completeVisit($resubmission, $this->vpf);
    expect(openVisit($request)->department_id)->toBe($this->org->chancellor->id);
});

test('REQ-05-08 every visit is kept with working days spent', function () {
    Carbon::setTestNow('2026-10-23 09:00:00');   // Friday
    $request = toVpFinance();
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    Carbon::setTestNow('2026-10-27 15:00:00');   // Tuesday
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->registrarOffice, 'Wrong name', 'Reprint');
    passOffice($request, $this->registrar);
    passOffice($request, $this->vpf);

    $vpfVisits = $request->visits()->where('department_id', $this->org->vpf->id)->orderBy('id')->get();

    expect($vpfVisits)->toHaveCount(2)
        ->and($vpfVisits[0])->status->toBe(VisitStatus::Returned)->working_days->toBe(3)   // Sat, Mon, Tue
        ->and($vpfVisits[1])->status->toBe(VisitStatus::Completed)->visit_no->toBe(2)->working_days->toBe(0)
        ->and($vpfVisits[0]->received_at->toDateTimeString())->toBe('2026-10-23 09:00:00');
});

test('REQ-05-09 office staff and the registrar record delay reasons without changing the status', function () {
    $request = toVpFinance();

    Livewire::actingAs($this->vpf)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->set('delayCategory', DelayCategory::DocumentaryStamp->value)
        ->set('delayReason', 'Stamps out of stock')
        ->set('delayCorrectiveAction', 'Ordered more')
        ->call('recordDelay')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::InProgress)
        ->and($request->delayNotes()->sole())
        ->category->toBe(DelayCategory::DocumentaryStamp)
        ->department_id->toBe($this->org->vpf->id)
        ->recorded_by->toBe($this->vpf->id);

    // The Dean has seen the document but no longer holds it.
    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $request])
        ->set('delayCategory', 'other')->set('delayReason', 'x')->call('recordDelay')->assertForbidden();
});

test('REQ-05-10 the registrar can bypass a step, but not the final check', function () {
    passOffice($this->request, $this->registrar);

    Livewire::actingAs($this->registrar)->test(Show::class, ['credentialRequest' => $this->request])
        ->call('bypass')->assertHasErrors('bypassReason')
        ->set('bypassReason', 'Dean on leave; approved by the VP')->call('bypass')->assertHasNoErrors();

    expect($this->request->steps()->where('department_id', $this->org->ccs->id)->sole()->status)->toBe(StepStatus::Bypassed)
        ->and(openVisit($this->request)->department_id)->toBe($this->org->vpf->id)
        ->and($this->request->events()->pluck('type')->all())->toContain(RequestEventType::StepBypassed);

    passOffice($this->request, $this->vpf);
    passOffice($this->request, $this->chancellor);
    expect(fn () => workflow()->bypassStep($this->request, $this->registrar, 'No'))->toThrow(InvalidTransition::class);
});

test('REQ-05-11 only the office concerned acts, and the first action wins', function () {
    $visit = openVisit($this->request);

    expect($this->dean->can('act', $visit))->toBeFalse()
        ->and($this->registrar->can('act', $visit))->toBeTrue();

    Livewire::actingAs($this->dean)->test(OfficeShow::class, ['credentialRequest' => $this->request])->assertNotFound();

    passOffice($this->request, $this->registrar);
    $deanVisit = openVisit($this->request);
    $other = User::factory()->departmentStaff($this->org->ccs)->create();

    expect($this->registrar->can('act', $deanVisit))->toBeFalse()
        ->and($other->can('act', $deanVisit))->toBeTrue();

    workflow()->acceptReceipt($deanVisit, $this->dean);
    expect(fn () => workflow()->acceptReceipt(OfficeVisit::find($deanVisit->id), $other))->toThrow(InvalidTransition::class);
});

test('REQ-05-12 requests being processed past their effective due date are overdue', function () {
    expect($this->request->isOverdue())->toBeFalse();

    Carbon::setTestNow(now()->addDays(30));
    expect($this->request->fresh()->isOverdue())->toBeTrue()
        ->and(CredentialRequest::overdue()->pluck('id')->all())->toBe([$this->request->id]);

    $this->actingAs($this->registrar)->get(route('registrar.requests', ['status' => 'overdue']))->assertSee($this->request->reference_no);
});

test('REQ-05-13 customer action pauses the clock; the original due date stays and an adjusted one is set', function () {
    Carbon::setTestNow('2026-10-09 10:00:00');
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);
    $due = $request->fresh()->due_at->toDateTimeString();

    Carbon::setTestNow('2026-10-12 09:00:00');   // Monday
    workflow()->requestCustomerAction($request, $this->registrar, 'Submit an affidavit of loss.');
    expect($request->fresh()->status)->toBe(RequestStatus::ReturnedForCustomerAction)
        ->and($request->fresh()->isOverdue())->toBeFalse();

    Carbon::setTestNow('2026-10-15 09:00:00');   // Thursday: 3 working days later
    workflow()->customerResponded($request, $this->owner, 'Brought it today.');

    $request = $request->fresh();
    expect($request->status)->toBe(RequestStatus::InProgress)
        ->and($request->due_at->toDateTimeString())->toBe($due)
        ->and($request->adjusted_due_at->toDateTimeString())->toBe('2026-10-21 23:59:59')   // 17th + 3 working days
        ->and($request->effective_due_at->toDateTimeString())->toBe('2026-10-21 23:59:59')
        ->and($request->pauses()->sole()->working_days)->toBe(3);
});

test('REQ-05-14 office actions are in the ledger', function () {
    passOffice($this->request, $this->registrar);

    expect($this->request->events()->pluck('type')->all())->toContain(
        RequestEventType::ReceiptAccepted, RequestEventType::StepCompleted, RequestEventType::DocumentForwarded,
    );
});

test('REQ-03-10 route edits after acceptance do not change accepted requests', function () {
    $this->org->tor->steps()->where('department_id', $this->org->vpf->id)->delete();
    $this->org->tor->resequenceSteps();

    expect($this->request->steps()->with('department')->get()->pluck('department.code')->all())->toBe(['REG', 'CCS', 'VPF', 'CHAN', 'REG']);

    $later = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    expect($later->steps()->with('department')->get()->pluck('department.code')->all())->toBe(['REG', 'CCS', 'CHAN', 'REG']);
});
