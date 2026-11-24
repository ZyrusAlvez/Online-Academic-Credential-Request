<?php

use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\OverrideStep;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Livewire\Registrar\Queue;
use App\Livewire\Registrar\Records\Show as RecordShow;
use App\Livewire\Student\Requests\Show as StudentShow;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\WorkingCalendar;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| The Admin records a stuck request's next step for whoever couldn't (spec 35).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->admin = User::factory()->admin()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
    $this->chancellor = User::factory()->departmentStaff($this->org->chancellor)->create();
});

afterEach(fn () => Carbon::setTestNow());

/** A request's record, as the Admin sees it unless someone else is given. */
function overridePage(CredentialRequest $request, ?User $as = null): Testable
{
    return Livewire::actingAs($as ?? test()->admin)->test(RecordShow::class, ['credentialRequest' => $request->fresh()]);
}

/**
 * Open the Override on the request's record and record its step, as the Admin: when it happened
 * (the dialog's default, now, unless given), a reason, and any other field of the dialog.
 *
 * @param  array<string, mixed>  $fields
 */
function adminOverride(CredentialRequest $request, ?string $at = null, array $fields = []): Testable
{
    $page = overridePage($request)->call('openOverride');

    if ($at !== null) {
        $page->set('overrideAt', $at);
    }

    $page->set('overrideReason', $fields['overrideReason'] ?? 'The office could not use the system');

    foreach (Arr::except($fields, 'overrideReason') as $field => $value) {
        $page->set($field, $value);
    }

    return $page->call('recordOverride');
}

/** A TOR accepted at $time and sent on to the Dean by the Registrar, not yet received there. */
function overrideTorAtDean(string $time = '2026-11-23 09:00:00'): CredentialRequest
{
    Carbon::setTestNow($time);
    $request = acceptRequest(submitRequest(test()->owner, test()->org->tor), test()->registrar);
    passOffice($request, test()->registrar);

    return $request->fresh();
}

/** A TOR through every office and the final check, by the offices themselves: ready for release. */
function overrideTorReady(): CredentialRequest
{
    $request = overrideTorAtDean(now()->toDateTimeString());

    foreach ([test()->dean, test()->vpf, test()->chancellor] as $staff) {
        passOffice($request, $staff);
    }

    workflow()->approveFinalCheck($request->fresh(), test()->registrar);

    return $request->fresh();
}

test('REQ-35-01 only the Admin overrides, and only while a step waits', function () {
    $request = overrideTorAtDean();

    expect($this->admin->can('override', $request))->toBeTrue();

    foreach ([$this->registrar, $this->dean, User::factory()->cashier()->create(), $this->owner] as $user) {
        expect($user->can('override', $request))->toBeFalse();
    }

    overridePage($request)->assertSeeHtml('data-override-button');
    overridePage($request, $this->registrar)->assertDontSeeHtml('data-override-button')
        ->call('openOverride')->assertForbidden();
    overridePage($request, $this->registrar)->set('overrideReason', 'Offline')->call('recordOverride')->assertForbidden();
    overridePage($request, $this->dean)->call('openOverride')->assertForbidden();

    expect(openVisit($request))->status->toBe(VisitStatus::InTransit);

    workflow()->startEvaluation($declined = submitRequest($this->owner, $this->org->tor), $this->registrar);
    workflow()->decline($declined, $this->registrar, 'Not eligible');
    expect($this->admin->can('override', $declined->fresh()))->toBeFalse();
    overridePage($declined)->assertDontSeeHtml('data-override-button');
});

test('REQ-35-02 the Admin moves the paper through every office to the final check, as the offices and the Registrar would', function () {
    Carbon::setTestNow('2026-11-23 09:00:00');
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    $steps = [];

    for ($i = 0; $i < 20 && ($step = OverrideStep::for($request->fresh())) !== OverrideStep::Release; $i++) {
        $steps[] = $step;
        adminOverride($request)->assertHasNoErrors()->assertDispatched('notify', message: 'Override recorded.');

        // An office's approval leaves the paper in Outgoing for the Registrar, as the office's own would.
        if ($step === OverrideStep::Approve) {
            Livewire::actingAs($this->registrar)->test(Queue::class, ['queue' => 'outgoing'])->assertSee($request->reference_no);
        }
    }

    expect($steps)->toBe([
        OverrideStep::Forward,                                                 // the Registrar's preparation
        OverrideStep::Receive, OverrideStep::Approve, OverrideStep::Forward,   // the Dean
        OverrideStep::Receive, OverrideStep::Approve, OverrideStep::Forward,   // VP Finance
        OverrideStep::Receive, OverrideStep::Approve, OverrideStep::Forward,   // the Chancellor
        OverrideStep::FinalCheck,
    ]);

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::ReadyForRelease)
        ->and($request->steps()->where('status', '!=', StepStatus::Completed)->exists())->toBeFalse()
        ->and($request->visits()->whereIn('status', VisitStatus::open())->exists())->toBeFalse()
        ->and($request->visits()->where('department_id', $this->org->ccs->id)->sole())
        ->received_by->toBe($this->admin->id)->done_by->toBe($this->admin->id)->completed_by->toBe($this->admin->id);
});

test('REQ-35-02 a returned document goes back, and its correction is approved and sent back, by override', function () {
    $request = overrideTorAtDean();
    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned');

    adminOverride($request)->assertHasNoErrors();   // carried back to the Dean
    expect(openVisit($request))->purpose->toBe(VisitPurpose::Correction)->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::InTransit);

    adminOverride($request);   // received
    adminOverride($request);   // corrected
    expect($request->events()->where('type', RequestEventType::Corrected)->sole()->actor_id)->toBe($this->admin->id);

    adminOverride($request);   // sent back to VP Finance, which receives it again
    expect(openVisit($request))->purpose->toBe(VisitPurpose::Resubmission)->department_id->toBe($this->org->vpf->id)->status->toBe(VisitStatus::InTransit);
});

test('REQ-35-03 the Admin validates an online payment, which accepts the request from when it happened', function () {
    Carbon::setTestNow('2026-11-20 09:00:00');
    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    uploadPayment($request);
    Carbon::setTestNow('2026-11-23 15:00:00');

    adminOverride($request, '2026-11-21T10:00')->assertHasNoErrors();

    $request->refresh();
    expect($request)->status->toBe(RequestStatus::AcceptedForProcessing)->accepted_by->toBe($this->admin->id)
        ->and($request->accepted_at->toDateTimeString())->toBe('2026-11-21 10:00:00')
        ->and($request->due_at->toDateTimeString())->toBe(app(WorkingCalendar::class)->addWorkingDays(Carbon::parse('2026-11-21 10:00:00'), 7)->toDateTimeString())
        ->and($request->paymentProofs()->sole())->status->toBe(PaymentStatus::Verified)->reviewed_by->toBe($this->admin->id)
        ->and($request->paymentProofs()->sole()->reviewed_at->toDateTimeString())->toBe('2026-11-21 10:00:00');
});

test('REQ-35-03 a walk-in payment takes its official receipt number and the amount paid', function () {
    $request = workflow()->submitWalkIn($this->registrar, ['credential_type_id' => $this->org->tor->id, 'purpose' => 'Employment', 'receiving_institution' => null], [
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Villanueva', 'sex' => 'male', 'email' => 'pedro@example.com',
        'contact_number' => '09171234567', 'academic_status' => AcademicStatus::Graduate->value, 'college_id' => $this->org->ccs->id,
        'program' => 'BS Computer Science', 'student_number' => null, 'attended_from' => 2018, 'attended_to' => 2022,
    ]);
    expect($request->status)->toBe(RequestStatus::PaymentVerification);

    adminOverride($request)->assertHasErrors(['overrideReceiptNo', 'overrideAmount']);
    expect($request->fresh()->status)->toBe(RequestStatus::PaymentVerification);

    adminOverride($request, fields: ['overrideReceiptNo' => 'OR-1234', 'overrideAmount' => '530'])->assertHasNoErrors();
    expect($request->fresh()->status)->toBe(RequestStatus::AcceptedForProcessing)
        ->and($request->paymentProofs()->sole())->official_receipt_no->toBe('OR-1234')->reviewed_by->toBe($this->admin->id);
});

test('REQ-35-04 the Admin marks it released to the owner, or to a representative checked at the counter', function () {
    Carbon::setTestNow('2026-11-23 09:00:00');
    $toOwner = overrideTorReady();

    adminOverride($toOwner)->assertHasNoErrors();
    expect($toOwner->fresh()->status)->toBe(RequestStatus::Released)
        ->and($toOwner->fresh()->release)->recipient_type->toBe(RecipientType::Owner)
        ->recipient_name->toBe($toOwner->requester_name)->released_by->toBe($this->admin->id);

    $toRepresentative = overrideTorReady();
    adminOverride($toRepresentative, fields: ['recipientType' => 'representative'])->assertHasErrors(['recipientName']);
    adminOverride($toRepresentative, fields: ['recipientType' => 'representative', 'recipientName' => 'Rosa Reyes'])->assertHasNoErrors();
    expect($toRepresentative->fresh()->release)->recipient_type->toBe(RecipientType::Representative)->recipient_name->toBe('Rosa Reyes');
});

test('REQ-35-05 unlocking sends the request back to Initial Evaluation, checks kept, for another registrar', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::CredentialRequest, CheckResult::Confirmed);

    adminOverride($request)->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Submitted)
        ->and($request->checks()->where('item', CheckItem::CredentialRequest)->sole()->result)->toBe(CheckResult::Confirmed)
        ->and($request->events()->where('type', RequestEventType::EvaluationUnlocked)->sole()->actor_id)->toBe($this->admin->id);

    $other = User::factory()->registrar()->create();
    workflow()->startEvaluation($request, $other);
    expect($request->fresh())->status->toBe(RequestStatus::UnderEvaluation)->evaluated_by->toBe($other->id);
});

test('REQ-35-06 nothing to override before the evaluation starts, while the requester has to act, or once closed', function () {
    $submitted = submitRequest($this->owner, $this->org->tor);
    $awaitingPayment = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);

    $clarification = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($clarification, $this->registrar);
    workflow()->recordCheck($clarification, $this->registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($clarification, $this->registrar, 'Name the receiving school');

    $compliance = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($compliance, $this->registrar);
    workflow()->requireOfficeVisit($compliance, $this->registrar, ['Completed clearance'], '');

    $cancelled = submitRequest($this->owner, $this->org->tor);
    workflow()->cancel($cancelled, $this->owner);

    $released = overrideTorReady();
    workflow()->release($released, $this->registrar, RecipientType::Owner);

    foreach ([$submitted, $awaitingPayment, $clarification, $compliance, $cancelled, $released] as $request) {
        expect(OverrideStep::for($request->fresh()))->toBeNull()
            ->and($this->admin->can('override', $request->fresh()))->toBeFalse();
    }

    overridePage($compliance)->assertDontSeeHtml('data-override-button');
});

test('REQ-35-07 an override needs a reason', function () {
    $request = overrideTorAtDean();

    adminOverride($request, fields: ['overrideReason' => ''])->assertHasErrors(['overrideReason']);

    expect(openVisit($request)->status)->toBe(VisitStatus::InTransit);
});

test('REQ-35-08 the time it happened can\'t be in the future or before the last entry; within its minute it takes that entry\'s time', function () {
    $request = overrideTorAtDean('2026-11-20 09:00:30');
    Carbon::setTestNow('2026-11-23 15:00:00');

    adminOverride($request, '2026-11-23T15:01')->assertHasErrors(['overrideAt']);
    adminOverride($request, 'yesterday')->assertHasErrors(['overrideAt']);
    adminOverride($request, '2026-11-20T08:59')->assertHasNoErrors()->assertDispatched('notify');
    expect(openVisit($request)->status)->toBe(VisitStatus::InTransit);

    adminOverride($request, '2026-11-20T09:00')->assertHasNoErrors();
    expect(openVisit($request)->received_at->toDateTimeString())->toBe('2026-11-20 09:00:30');
});

test('REQ-35-09 each step takes the time it happened: the visits, deadline, working days, ready and release', function () {
    $request = overrideTorAtDean('2026-11-20 09:00:00');   // accepted and sent to the Dean on Friday
    Carbon::setTestNow('2026-11-23 15:00:00');              // Monday: the Admin catches up from the paper log

    foreach ([
        '2026-11-20T14:00', '2026-11-21T10:00', '2026-11-21T11:00',   // the Dean: received, signed, forwarded
        '2026-11-21T13:00', '2026-11-21T14:00', '2026-11-21T15:00',   // VP Finance
        '2026-11-21T16:00', '2026-11-23T08:00', '2026-11-23T09:00',   // the Chancellor, then to the final check
        '2026-11-23T10:00',                                            // final check: ready for release
        '2026-11-23T11:00',                                            // released to the owner
    ] as $at) {
        adminOverride($request, $at)->assertHasNoErrors();
    }

    $calendar = app(WorkingCalendar::class);
    $dean = $request->visits()->where('department_id', $this->org->ccs->id)->with('step')->sole();
    expect($dean->received_at->toDateTimeString())->toBe('2026-11-20 14:00:00')
        ->and($dean->due_at->toDateTimeString())->toBe($calendar->addWorkingDays(Carbon::parse('2026-11-20 14:00:00'), (int) $dean->step->allotted_days)->toDateTimeString())
        ->and($dean->done_at->toDateTimeString())->toBe('2026-11-21 10:00:00')
        ->and($dean->step->completed_at->toDateTimeString())->toBe('2026-11-21 10:00:00')
        ->and($dean->completed_at->toDateTimeString())->toBe('2026-11-21 11:00:00')
        ->and($dean->working_days)->toBe(1)
        ->and($request->visits()->where('department_id', $this->org->vpf->id)->sole()->forwarded_at->toDateTimeString())->toBe('2026-11-21 11:00:00')
        ->and($request->events()->where('type', RequestEventType::ReceiptAccepted)->where('department_id', $this->org->ccs->id)->sole()->created_at->toDateTimeString())->toBe('2026-11-20 14:00:00');

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Released)
        ->and($request->ready_at->toDateTimeString())->toBe('2026-11-23 10:00:00')
        ->and($request->processing_working_days)->toBe(2)   // Saturday and Monday
        ->and($request->released_at->toDateTimeString())->toBe('2026-11-23 11:00:00')
        ->and($request->release->released_at->toDateTimeString())->toBe('2026-11-23 11:00:00');
});

test('REQ-35-10 the entries carry the Admin, the reason and when they were recorded; staff see it, the requester does not', function () {
    $request = overrideTorAtDean('2026-11-20 09:00:00');
    Carbon::setTestNow('2026-11-23 15:00:00');

    adminOverride($request, '2026-11-20T14:00', ['overrideReason' => 'The college had no internet']);

    $entry = $request->events()->where('type', RequestEventType::ReceiptAccepted)->where('department_id', $this->org->ccs->id)->sole();
    expect($entry)->actor_id->toBe($this->admin->id)->override_reason->toBe('The college had no internet')
        ->and($entry->recorded_at->toDateTimeString())->toBe('2026-11-23 15:00:00')
        ->and($entry->created_at->toDateTimeString())->toBe('2026-11-20 14:00:00')
        ->and($request->events()->whereKeyNot($entry->id)->whereNotNull('override_reason')->exists())->toBeFalse();

    overridePage($request, $this->registrar)->assertSeeHtml('data-override-entry')->assertSee('The college had no internet');
    Livewire::actingAs($this->owner)->test(StudentShow::class, ['credentialRequest' => $request->fresh()])
        ->assertDontSeeHtml('data-override-entry')->assertDontSee('The college had no internet');
});

test('REQ-35-11 the activity log has each entry when it was recorded, with the reason and when it happened', function () {
    $request = overrideTorAtDean('2026-11-20 09:00:00');
    Carbon::setTestNow('2026-11-23 15:00:00');

    adminOverride($request, '2026-11-20T14:00', ['overrideReason' => 'The college had no internet']);

    $log = ActivityLog::query()->where('action', 'request.receipt_accepted')->where('department_id', $this->org->ccs->id)->sole();
    expect($log)->actor_id->toBe($this->admin->id)
        ->and($log->created_at->toDateTimeString())->toBe('2026-11-23 15:00:00')
        ->and($log->description)->toContain('Admin override (happened Nov 20, 2026 2:00 PM)')->toContain('The college had no internet');
});

test('REQ-35-12 an override notifies as its step would; an unlock tells every registrar, not the requester', function () {
    Notification::fake();
    $request = overrideTorAtDean();
    $collect = fn (CredentialRequest $request, string $office) => fn (RequestActivity $n) => $n->title === "{$request->reference_no} is ready to collect from {$office}";

    adminOverride($request);   // received at the Dean
    adminOverride($request);   // approved there: it waits in Outgoing
    Notification::assertSentTo($this->registrar, RequestActivity::class, $collect($request, 'College of Computer Studies'));

    adminOverride($request);   // forwarded to VP Finance
    Notification::assertSentTo($this->vpf, RequestActivity::class, fn (RequestActivity $n) => $n->title === "{$request->reference_no} was forwarded to your office");

    // The Registrar's own preparation, forwarded at once: nothing to collect.
    $accepted = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    adminOverride($accepted)->assertHasNoErrors();
    expect(openVisit($accepted)->department_id)->toBe($this->org->ccs->id);
    Notification::assertNotSentTo($this->registrar, RequestActivity::class, fn (RequestActivity $n) => str_starts_with($n->title, "{$accepted->reference_no} is ready to collect"));

    $other = User::factory()->registrar()->create();
    $locked = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($locked, $this->registrar);
    adminOverride($locked);

    foreach ([$this->registrar, $other] as $registrar) {
        Notification::assertSentTo($registrar, RequestActivity::class, fn (RequestActivity $n) => $n->title === "{$locked->reference_no} is back in Initial Evaluation");
    }
    Notification::assertNotSentTo($this->owner, RequestActivity::class, fn (RequestActivity $n) => $n->event->type === RequestEventType::EvaluationUnlocked);
});

test('REQ-35-13 a step recorded meanwhile refuses the override, which then offers the request\'s new step', function () {
    $request = overrideTorAtDean();
    $page = overridePage($request)->call('openOverride')->assertSet('overrideStep', OverrideStep::Receive->value);

    workflow()->acceptReceipt(openVisit($request), $this->dean);   // the college is back online

    $page->set('overrideReason', 'Offline')->call('recordOverride')
        ->assertDispatched('notify', message: 'The request has moved on since the Override was opened.')
        ->assertSet('overrideStep', OverrideStep::Approve->value);
    expect(openVisit($request))->status->toBe(VisitStatus::Received)->received_by->toBe($this->dean->id)
        ->and($request->events()->whereNotNull('override_reason')->exists())->toBeFalse();

    // The same kind of step, at another office since: refused too.
    $accepted = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    $page = overridePage($accepted)->call('openOverride')->assertSet('overrideStep', OverrideStep::Forward->value);
    passOffice($accepted, $this->registrar);
    workflow()->acceptReceipt(openVisit($accepted), $this->dean);
    workflow()->completeVisit(openVisit($accepted), $this->dean);

    $page->set('overrideReason', 'Offline')->call('recordOverride')
        ->assertDispatched('notify', message: 'The request has moved on since the Override was opened.');
    expect(openVisit($accepted))->department_id->toBe($this->org->ccs->id)->status->toBe(VisitStatus::Done);
});
