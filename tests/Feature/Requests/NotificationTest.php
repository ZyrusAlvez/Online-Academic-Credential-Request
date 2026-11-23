<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\RequestEventType;
use App\Livewire\NotificationBell;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Support\RequestRegistrars;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->inactiveRegistrar = User::factory()->registrar()->inactive()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();
});

function requesterEventTypes(): array
{
    return Notification::sent(test()->owner, RequestActivity::class)
        ->map(fn (RequestActivity $n) => $n->event->type)->all();
}

function mailedTypes(): array
{
    return Notification::sent(test()->owner, RequestActivity::class)
        ->filter(fn (RequestActivity $n) => in_array('mail', $n->via(test()->owner), true))
        ->map(fn (RequestActivity $n) => $n->event->type)->values()->all();
}

test('REQ-06-05 the requester is emailed at the client\'s key moments', function () {
    Notification::fake();

    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->returnForClarification($request, $this->registrar, 'Correct your years of attendance.');
    workflow()->resubmit($request, $this->owner);   // their own action: not sent to them
    evaluate($request, $this->registrar);
    uploadPayment($request);
    workflow()->rejectPayment($request, $this->registrar, 'No matching transaction');
    uploadPayment($request);
    workflow()->verifyPaymentAndAccept($request, $this->registrar);

    expect(mailedTypes())->toContain(
        RequestEventType::Submitted,
        RequestEventType::CustomerActionRequested,
        RequestEventType::RequirementsCompleted,
        RequestEventType::PaymentRejected,
        RequestEventType::Accepted,
    )->and(mailedTypes())->toBe(requesterEventTypes())
        ->and(mailedTypes())->not->toContain(RequestEventType::Resubmitted);
});

test('REQ-06-06 other milestones are emailed too; internal entries and own actions are not sent', function () {
    Notification::fake();

    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);

    $types = requesterEventTypes();
    expect($types)->toContain(RequestEventType::EvaluationStarted, RequestEventType::ReceiptAccepted, RequestEventType::StepCompleted)
        ->not->toContain(RequestEventType::DocumentForwarded, RequestEventType::PaymentSubmitted)
        ->and(mailedTypes())->toContain(RequestEventType::EvaluationStarted, RequestEventType::ReceiptAccepted, RequestEventType::StepCompleted);
});

test('REQ-06-07 offices are told in-app and by email when a document is forwarded or returned to them', function () {
    Notification::fake();
    $other = User::factory()->registrar()->create();
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    // At the Office of the Registrar, only the request's evaluator (REQ-06-21).
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office");
    Notification::assertNotSentTo($other, RequestActivity::class, fn ($n) => str_contains($n->title, 'forwarded to your office'));
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);

    passOffice($request, $this->registrar);
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office"
        && $n->via($this->dean) === ['database', 'mail']);

    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned', 'Sign it');
    Notification::assertNotSentTo($this->dean, RequestActivity::class, fn ($n) => str_contains($n->title, 'returned to your office'));
    workflow()->forwardDocument(openVisit($request), $this->registrar);   // told when the Registrar brings it (REQ-32-08)
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was returned to your office for correction");

    passOffice($request, $this->dean);
    Notification::assertSentTo($this->vpf, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was corrected and sent back to you");
});

test('REQ-06-08 every registrar is told about a new request, its evaluator about resubmissions and representatives; cashiers about payments', function () {
    Notification::fake();
    $cashier = User::factory()->cashier()->create();
    $other = User::factory()->registrar()->create();

    $request = submitRequest($this->owner, $this->org->tor);
    Notification::assertSentTo([$this->registrar, $other], RequestActivity::class, fn ($n) => $n->title === "New request {$request->reference_no} to evaluate"
        && $n->via($this->registrar) === ['database', 'mail']);

    evaluate($request, $this->registrar);
    uploadPayment($request);
    // A proof of payment is the cashier's to validate (REQ-25-08).
    Notification::assertSentTo($cashier, RequestActivity::class, fn ($n) => $n->title === "Payment to verify for {$request->reference_no}");
    Notification::assertNotSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Payment to verify for {$request->reference_no}");

    declareRepresentative($request, $this->owner, ['full_name' => 'Rosa']);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Representative to verify for {$request->reference_no}");
    Notification::assertNotSentTo($other, RequestActivity::class, fn ($n) => $n->title === "Representative to verify for {$request->reference_no}");

    $returned = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($returned, $this->registrar);
    workflow()->recordCheck($returned, $this->registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($returned, $this->registrar, 'Give the full name of the school.');
    workflow()->resubmit($returned, $this->owner, 'Done');
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "{$returned->reference_no} was resubmitted for evaluation");
    Notification::assertNotSentTo($other, RequestActivity::class, fn ($n) => $n->title === "{$returned->reference_no} was resubmitted for evaluation");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);
});

test('REQ-06-21 a request whose evaluator is inactive, or that has none, goes to every active registrar', function () {
    Notification::fake();
    $other = User::factory()->registrar()->create();

    expect(RequestRegistrars::for(submitRequest($this->owner, $this->org->tor))->modelKeys())
        ->toEqualCanonicalizing([$this->registrar->id, $other->id]);

    $request = evaluate(submitRequest($this->owner, $this->org->tor), $this->registrar);
    expect(RequestRegistrars::for($request->fresh())->modelKeys())->toBe([$this->registrar->id]);

    $this->registrar->forceFill(['is_active' => false])->save();
    declareRepresentative($request, $this->owner, ['full_name' => 'Rosa']);
    Notification::assertSentTo($other, RequestActivity::class, fn ($n) => $n->title === "Representative to verify for {$request->reference_no}");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);
});

test('REQ-06-18 notifications the requester must act on are [ACTION REQUIRED] and emailed even with email off', function () {
    $this->owner->forceFill(['email_notifications' => false])->save();
    Notification::fake();

    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->returnForClarification($request, $this->registrar, 'Correct your years of attendance.');
    workflow()->resubmit($request, $this->owner);
    evaluate($request, $this->registrar);   // payment instructions: an amount is due
    uploadPayment($request);
    workflow()->rejectPayment($request, $this->registrar, 'No matching transaction');

    $sent = Notification::sent($this->owner, RequestActivity::class);
    $required = $sent->filter(fn (RequestActivity $n) => $n->actionRequired($this->owner))->map(fn ($n) => $n->event->type)->values()->all();

    expect($required)->toBe([RequestEventType::CustomerActionRequested, RequestEventType::RequirementsCompleted, RequestEventType::PaymentRejected])
        ->and(mailedTypes())->toBe($required);   // informational ones are not mailed while email is off

    $paymentRejected = $sent->first(fn ($n) => $n->event->type === RequestEventType::PaymentRejected);
    expect($paymentRejected->toMail($this->owner)->subject)->toBe("[ACTION REQUIRED] {$request->reference_no}: {$paymentRejected->title}")
        ->and($paymentRejected->toArray($this->owner)['action_required'])->toBeTrue();

    $evaluationStarted = $sent->first(fn ($n) => $n->event->type === RequestEventType::EvaluationStarted);
    expect($evaluationStarted->toMail($this->owner)->subject)->not->toContain('[ACTION REQUIRED]')
        ->and($evaluationStarted->toArray($this->owner)['action_required'])->toBeFalse();
});

test('REQ-06-18 the action list: office visit, ID, representative and claiming; a free credential has no payment to make', function () {
    $paid = submitRequest($this->owner, $this->org->tor);
    $paid->forceFill(['amount_due' => 150])->save();
    $free = submitRequest($this->owner, $this->org->free);
    $free->forceFill(['amount_due' => 0])->save();

    foreach ([RequestEventType::ComplianceRequested, RequestEventType::IdentityRejected, RequestEventType::RepresentativeRejected, RequestEventType::ReadyForRelease, RequestEventType::CustomerActionRequested, RequestEventType::PaymentRejected] as $type) {
        expect($type->requiresRequesterAction($paid))->toBeTrue();
    }

    expect(RequestEventType::RequirementsCompleted->requiresRequesterAction($paid))->toBeTrue()
        ->and(RequestEventType::RequirementsCompleted->requiresRequesterAction($free))->toBeFalse()
        ->and(RequestEventType::Accepted->requiresRequesterAction($paid))->toBeFalse()
        ->and(RequestEventType::ReceiptAccepted->requiresRequesterAction($paid))->toBeFalse();
});

test('REQ-06-09 users cannot open other users\' notifications', function () {
    submitRequest($this->owner, $this->org->tor);
    $foreign = $this->owner->notifications()->first();

    Livewire::actingAs(requester($this->org->cba))->test(NotificationBell::class)
        ->call('open', $foreign->id)
        ->assertNotFound();
});

test('REQ-06-10 notifications go out right after the response, with no queue worker', function () {
    expect((require config_path('queue.php'))['default'])->toBe('deferred');
    config(['queue.default' => 'deferred']);
    $channels = collect();
    Event::listen(NotificationSent::class, fn (NotificationSent $event) => $channels->push($event->channel));

    submitRequest($this->owner, $this->org->tor);

    // Nothing is sent while the action runs, and nothing waits in the jobs table for a worker.
    expect(DB::table('jobs')->count())->toBe(0)
        ->and($channels)->toBeEmpty();

    app(DeferredCallbackCollection::class)->invoke();   // what Laravel does once the response is sent

    expect($this->registrar->notifications()->count())->toBe(1)
        ->and($this->owner->notifications()->count())->toBe(1)
        ->and($channels->countBy()->all())->toBe(['database' => 2, 'mail' => 2]);
});

test('REQ-06-10 a notification that fails to send is logged and the others still go out', function () {
    Exceptions::fake();
    config(['queue.default' => 'deferred']);
    Event::listen(NotificationSending::class, function (NotificationSending $event) {
        throw_if($event->notifiable->is($this->registrar), new RuntimeException('Gmail is down'));
    });

    submitRequest($this->owner, $this->org->tor);
    app(DeferredCallbackCollection::class)->invoke();

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Gmail is down');
    expect($this->registrar->notifications()->count())->toBe(0)
        ->and($this->owner->notifications()->count())->toBe(1);
});
