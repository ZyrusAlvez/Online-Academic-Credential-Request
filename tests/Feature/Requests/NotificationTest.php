<?php

use App\Enums\RequestEventType;
use App\Livewire\NotificationBell;
use App\Livewire\Notifications\Index;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Services\RequestWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = organisation();
    $this->workflow = app(RequestWorkflow::class);
    $this->student = User::factory()->student()->create();
    $this->registrar = User::factory()->registrar()->create();
    $this->inactiveRegistrar = User::factory()->registrar()->inactive()->create();
    $this->librarian = User::factory()->departmentStaff($this->org->library)->create();
    $this->cashier = User::factory()->departmentStaff($this->org->cashier)->create();
});

function studentEventTypes(): array
{
    return Notification::sent(test()->student, RequestActivity::class)
        ->map(fn (RequestActivity $n) => $n->event->type)->all();
}

test('REQ-06-05 the student is notified of events except their own actions and internal ones', function () {
    Notification::fake();
    Storage::fake('local');

    $request = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    $this->workflow->submitPaymentProof($request, $this->student, UploadedFile::fake()->image('r.jpg'));
    $this->workflow->approve($request, $this->registrar);
    $this->workflow->sign($request->currentStep()->firstOrFail(), $this->librarian);

    expect(studentEventTypes())->toBe([
        RequestEventType::Submitted,
        RequestEventType::Routed,
        RequestEventType::StepSigned,
    ]);
});

test('REQ-06-06 key events are emailed to the student, others are in-app only', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);
    $events = $request->events()->with('department')->get();

    $routed = new RequestActivity($events->firstWhere('type', RequestEventType::Routed), 'Approved');
    expect($routed->via($this->student))->toBe(['database', 'mail'])
        ->and($routed->via($this->registrar))->toBe(['database']);

    $mail = $routed->toMail($this->student);
    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toContain($request->reference_no)
        ->and($mail->actionUrl)->toBe(route('student.requests.show', $request));

    $step = $request->currentStep()->firstOrFail();
    $this->workflow->hold($step, $this->registrar, 'Missing documents');
    $this->workflow->resume($step->fresh(), $this->registrar);
    $resumed = $request->events()->get()->last();

    expect((new RequestActivity($resumed, 'Hold lifted'))->via($this->student))->toBe(['database']);
});

test('REQ-06-07 the office a request reaches is notified', function () {
    Notification::fake();

    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $request->forceFill(['credential_type_id' => $this->org->tor->id])->save();
    $this->workflow->approve($request, $this->registrar);

    Notification::assertSentTo($this->librarian, RequestActivity::class, fn ($n) => $n->title === "{$request->reference_no} is waiting for your signature");
    Notification::assertNotSentTo($this->cashier, RequestActivity::class);

    $this->workflow->sign($request->currentStep()->firstOrFail(), $this->librarian);
    Notification::assertSentTo($this->cashier, RequestActivity::class);
});

test('REQ-06-07 registrars are notified when a request reaches the Office of the Registrar', function () {
    Notification::fake();

    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);

    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => str_contains($n->title, 'waiting for your signature'));
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);
});

test('REQ-06-08 active registrars are notified when a request needs review', function () {
    Notification::fake();
    Storage::fake('local');

    $free = submitRequest($this->student, $this->org->free, $this->org->ccs);
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "New request {$free->reference_no} to review");

    $paid = submitRequest($this->student, $this->org->tor, $this->org->ccs);
    Notification::assertNotSentTo($this->registrar, RequestActivity::class, fn ($n) => str_contains($n->title, $paid->reference_no));

    $this->workflow->submitPaymentProof($paid, $this->student, UploadedFile::fake()->image('r.jpg'));
    Notification::assertSentTo($this->registrar, RequestActivity::class, fn ($n) => $n->title === "Receipt to verify for {$paid->reference_no}");
    Notification::assertNotSentTo($this->inactiveRegistrar, RequestActivity::class);
});

test('REQ-06-09 the bell shows unread notifications; opening one marks it read and navigates', function () {
    $request = submitRequest($this->student, $this->org->free, $this->org->ccs);
    $this->workflow->approve($request, $this->registrar);

    expect($this->student->unreadNotifications()->count())->toBe(2);

    $bell = Livewire::actingAs($this->student)->test(NotificationBell::class)
        ->assertSee('Approved and sent to the signing offices')
        ->assertSee('Notifications, 2 unread', false);

    $latest = $this->student->notifications()->first();
    $bell->call('open', $latest->id)->assertRedirect(route('student.requests.show', $request));

    expect($this->student->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($this->student)->test(Index::class)->call('markAllRead');
    expect($this->student->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->student)->get(route('notifications'))->assertOk()->assertSee($request->reference_no);
});

test('REQ-06-09 users cannot open other users\' notifications', function () {
    submitRequest($this->student, $this->org->free, $this->org->ccs);
    $foreign = $this->student->notifications()->first();

    Livewire::actingAs(User::factory()->student()->create())->test(NotificationBell::class)
        ->call('open', $foreign->id)
        ->assertNotFound();
});

test('REQ-06-10 request notifications are queued', function () {
    expect(new RequestActivity(submitRequest($this->student, $this->org->free, $this->org->ccs)->events()->first(), 't'))
        ->toBeInstanceOf(ShouldQueue::class);
});
