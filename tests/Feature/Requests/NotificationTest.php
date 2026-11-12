<?php

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
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
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->returnForClarification($request, $this->registrar, 'Correct your years of attendance.');
    workflow()->resubmit($request, $this->owner);   // their own action: not sent to them
    evaluate($request, $this->registrar);
    uploadPayment($request);
    workflow()->rejectPayment($request, $this->registrar, 'No matching transaction');
    uploadPayment($request);
    checkAll($request, $this->registrar, CheckStage::Payment);
    workflow()->verifyPaymentAndAccept($request, $this->registrar, 'OR-1');

    expect(mailedTypes())->toContain(
        RequestEventType::Submitted,
        RequestEventType::CustomerActionRequested,
        RequestEventType::RequirementsCompleted,
        RequestEventType::PaymentRejected,
        RequestEventType::Accepted,
    )->and(mailedTypes())->toBe(requesterEventTypes())
        ->and(mailedTypes())->not->toContain(RequestEventType::Resubmitted);
});

test('REQ-06-05 ready-for-release and declined are emailed with the details', function () {
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
    workflow()->startEvaluation($declined, $this->registrar);
    workflow()->decline($declined, $this->registrar, 'Not eligible');

    expect(mailedTypes())->toContain(RequestEventType::Declined);
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
    $request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);

    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);

    passOffice($request, $this->registrar);
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was forwarded to your office"
        && $n->via($this->dean) === ['database', 'mail']);

    passOffice($request, $this->dean);
    workflow()->acceptReceipt(openVisit($request), $this->vpf);
    workflow()->returnVisit(openVisit($request), $this->vpf, $this->org->ccs, 'Unsigned', 'Sign it');
    Notification::assertSentTo($this->dean, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was returned to your office for correction");

    passOffice($request, $this->dean);
    Notification::assertSentTo($this->vpf, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} was corrected and sent back to you");
});

test('REQ-06-08 registrars are told in-app and by email about new and resubmitted requests, payments and representatives', function () {
    Notification::fake();

    $request = submitRequest($this->owner, $this->org->tor);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "New request {$request->reference_no} to evaluate"
        && $n->via($this->registrar) === ['database', 'mail']);

    evaluate($request, $this->registrar);
    uploadPayment($request);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Payment to verify for {$request->reference_no}");

    declareRepresentative($request, $this->owner, ['full_name' => 'Rosa']);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Representative to verify for {$request->reference_no}");

    $other = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($other, $this->registrar);
    workflow()->recordCheck($other, $this->registrar, CheckItem::CredentialRequest, CheckResult::Rejected);
    workflow()->returnForClarification($other, $this->registrar, 'Give the full name of the school.');
    workflow()->resubmit($other, $this->owner, 'Done');
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "{$other->reference_no} was resubmitted for evaluation");
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

test('REQ-06-18 the bell and the Notifications page show an Action required badge', function () {
    $request = submitRequest($this->owner, $this->org->tor);
    workflow()->startEvaluation($request, $this->registrar);
    workflow()->recordCheck($request, $this->registrar, CheckItem::AcademicRecord, CheckResult::Rejected);
    workflow()->returnForClarification($request, $this->registrar, 'Correct your years of attendance.');

    Livewire::actingAs($this->owner)->test(NotificationBell::class)
        ->assertSeeInOrder(['Action required', 'Returned to you for clarification'])
        ->assertSeeHtml('data-action-required');
    Livewire::actingAs($this->owner)->test(Index::class)
        ->assertSeeInOrder(['Action required', 'Returned to you for clarification']);
    expect($this->owner->notifications()->get()->filter(fn ($n) => $n->data['action_required'])->count())->toBe(1);

    // Staff never see it.
    Livewire::actingAs($this->registrar)->test(NotificationBell::class)->assertDontSeeHtml('data-action-required');
});

test('REQ-06-19 staff get their own copy and an email that names the request and links to their page', function () {
    $otherRegistrar = User::factory()->registrar()->create();
    $request = submitRequest($this->owner, $this->org->tor);

    expect($this->registrar->unreadNotifications()->count())->toBe(1)
        ->and($otherRegistrar->unreadNotifications()->count())->toBe(1);

    $this->registrar->unreadNotifications()->first()->markAsRead();
    expect($otherRegistrar->unreadNotifications()->count())->toBe(1);   // read status is per account

    $submitted = $request->events()->first();
    $mail = (new RequestActivity($submitted, "New request {$request->reference_no} to evaluate"))->toMail($this->registrar);
    expect($mail->subject)->toBe("{$request->reference_no}: New request {$request->reference_no} to evaluate")
        ->and(implode(' ', $mail->introLines))->toContain($request->credentialType->name, $this->owner->fullName(), 'Request received for evaluation', 'Current location:')
        ->and($mail->actionText)->toBe('Open request')
        ->and($mail->actionUrl)->toBe(route('registrar.requests.show', $request));

    $this->dean->forceFill(['email_notifications' => false])->save();
    expect((new RequestActivity($submitted, 't'))->via($this->dean))->toBe(['database']);
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
