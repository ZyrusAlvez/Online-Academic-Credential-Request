<?php

use App\Enums\RequestEventType;
use App\Livewire\NotificationBell;
use App\Livewire\Notifications\Index;
use App\Models\User;
use App\Notifications\RequestActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
    workflow()->requestCompliance($request, $this->registrar, ['Original Form 137']);
    workflow()->resolveDeficiency($request->deficiencies()->sole(), $this->registrar, 'Received');
    workflow()->completeEvaluation($request, $this->registrar);
    uploadPayment($request);
    workflow()->rejectPayment($request, $this->registrar, 'No matching transaction');
    uploadPayment($request);
    workflow()->verifyPaymentAndAccept($request, $this->registrar, 'OR-1');

    expect(mailedTypes())->toBe([
        RequestEventType::Submitted,
        RequestEventType::ComplianceRequested,
        RequestEventType::RequirementsCompleted,
        RequestEventType::PaymentRejected,
        RequestEventType::Accepted,
    ]);
});

test('REQ-06-05 ready-for-release, declined and cancelled are emailed with the details', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);
    passOffice($request, $this->registrar);
    passOffice($request, $this->registrar);
    $ready = $request->events()->with(['department', 'step'])->get()->firstWhere('type', RequestEventType::ReadyForRelease);

    $mail = (new RequestActivity($ready, 'Ready'))->toMail($this->owner);
    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toContain($request->reference_no)
        ->and(implode(' ', $mail->introLines))->toContain('Special Power of Attorney')
        ->and($mail->actionUrl)->toBe(route('student.requests.show', $request));

    Notification::fake();
    $declined = submitRequest($this->owner, $this->org->tor);
    workflow()->decline($declined, $this->registrar, 'Not eligible');
    $cancelled = submitRequest($this->owner, $this->org->tor);
    workflow()->cancel($cancelled, $this->registrar, 'Duplicate');

    expect(mailedTypes())->toContain(RequestEventType::Declined, RequestEventType::Cancelled);
});

test('REQ-06-15 emails carry the UPHSL crest, brand colours and the registrar footer', function () {
    $request = acceptRequest(submitRequest($this->owner, $this->org->free), $this->registrar);
    $accepted = $request->events()->with(['department', 'step'])->get()->firstWhere('type', RequestEventType::Accepted);

    $html = (string) (new RequestActivity($accepted, 'Accepted'))->toMail($this->owner)->render();

    expect($html)->toContain(asset('images/uphsl-logo-email.png'))
        ->toContain('background-color: #012265')
        ->toContain('background-color: #eef3fb')
        ->toContain(config('mail.from.name'))
        ->toContain('released in person')
        ->and(file_exists(public_path('images/uphsl-logo-email.png')))->toBeTrue();
});

test('REQ-06-06 other milestones are in-app only; internal entries and own actions are not sent', function () {
    Notification::fake();

    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($request, $this->registrar);

    $types = requesterEventTypes();
    expect($types)->toContain(RequestEventType::EvaluationStarted, RequestEventType::ReceiptAccepted, RequestEventType::StepCompleted)
        ->not->toContain(RequestEventType::DocumentForwarded, RequestEventType::PaymentSubmitted)
        ->and(mailedTypes())->not->toContain(RequestEventType::EvaluationStarted, RequestEventType::ReceiptAccepted);
});

test('REQ-06-07 offices are told when a document is forwarded or returned to them', function () {
    Notification::fake();
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);

    passOffice($request, $this->registrar);
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office");

    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned', 'Sign it');
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was returned to your office for correction");

    passOffice($request, $this->dean);
    Notification::assertSentTo($this->vpf, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was corrected and sent back to you");
});

test('REQ-06-08 registrars are told about new requests, payments, replies and representatives', function () {
    Notification::fake();

    $request = submitRequest($this->owner, $this->org->tor);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "New request {$request->reference_no} to evaluate");

    evaluate($request, $this->registrar);
    uploadPayment($request);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Payment to verify for {$request->reference_no}");

    workflow()->declareRepresentative($request, $this->owner, ['full_name' => 'Rosa']);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Representative to verify for {$request->reference_no}");

    $other = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($other, $this->registrar);
    workflow()->requestCustomerAction($other, $this->registrar, 'Clarify');
    workflow()->customerResponded($other, $this->owner, 'Done');
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "The requester replied on {$other->reference_no}");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);
});

test('REQ-06-09 the bell shows unread notifications; opening one marks it read and navigates', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);

    expect($this->owner->unreadNotifications()->count())->toBe(2);

    $bell = Livewire::actingAs($this->owner)->test(NotificationBell::class)
        ->assertSee('Evaluation started by the Office of the Registrar')
        ->assertSee('Notifications, 2 unread', false);

    $latest = $this->owner->notifications()->first();
    $bell->call('open', $latest->id)->assertRedirect(route('student.requests.show', $request));

    expect($this->owner->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($this->owner)->test(Index::class)->call('markAllRead');
    expect($this->owner->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->owner)->get(route('notifications'))->assertOk()->assertSee($request->reference_no);
});

test('REQ-06-09 users cannot open other users\' notifications', function () {
    submitRequest($this->owner, $this->org->tor);
    $foreign = $this->owner->notifications()->first();

    Livewire::actingAs(requester($this->org->cba))->test(NotificationBell::class)
        ->call('open', $foreign->id)
        ->assertNotFound();
});

test('REQ-06-10 request notifications are queued', function () {
    expect(new RequestActivity(submitRequest($this->owner, $this->org->tor)->events()->first(), 't'))
        ->toBeInstanceOf(ShouldQueue::class);
});
