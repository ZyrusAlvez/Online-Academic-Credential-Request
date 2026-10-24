<?php

use App\Models\User;
use App\Notifications\DueReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create();
    $this->dean = User::factory()->departmentStaff($this->org->ccs)->create();
    $this->vpf = User::factory()->departmentStaff($this->org->vpf)->create();

    Carbon::setTestNow('2026-10-09 10:00:00');   // Friday; 7 working days → due Sat Oct 17
    $this->request = acceptRequest(submitRequest($this->owner, $this->org->tor), $this->registrar);
    passOffice($this->request, $this->registrar);   // now with the Dean
    Notification::fake();
});

afterEach(fn () => Carbon::setTestNow());

function remindAt(string $time): void
{
    Carbon::setTestNow($time);
    test()->artisan('app:send-due-reminders')->assertSuccessful();
}

test('REQ-06-11 the holding office is reminded the working day before the due date, once', function () {
    remindAt('2026-10-15 07:00:00');   // Thursday: due Saturday, not yet within a working day
    Notification::assertNothingSent();

    remindAt('2026-10-16 07:00:00');   // Friday: due the next working day
    remindAt('2026-10-16 08:00:00');   // rerun

    Notification::assertSentToTimes($this->dean, DueReminder::class, 1);
    Notification::assertSentTo($this->dean, DueReminder::class, fn (DueReminder $n) => ! $n->overdue);
    Notification::assertNotSentTo($this->owner, DueReminder::class);
});

test('REQ-06-12 the holding office is alerted once when the document is overdue, and so is the next office', function () {
    remindAt('2026-10-19 07:00:00');   // Monday: overdue since Saturday night
    remindAt('2026-10-19 07:30:00');

    Notification::assertSentToTimes($this->dean, DueReminder::class, 1);
    Notification::assertSentTo($this->dean, DueReminder::class, fn (DueReminder $n) => $n->overdue && $n->toArray($this->dean)['title'] === "{$this->request->reference_no} is overdue");

    workflow()->acceptReceipt(openVisit($this->request), $this->dean);
    workflow()->completeVisit(openVisit($this->request), $this->dean);   // forwarded to VP Finance
    remindAt('2026-10-20 07:00:00');

    Notification::assertSentToTimes($this->vpf, DueReminder::class, 1);
    Notification::assertSentToTimes($this->dean, DueReminder::class, 1);
});

test('REQ-06-13 the requester is told once, in-app and by email, when the request is overdue', function () {
    remindAt('2026-10-19 07:00:00');
    remindAt('2026-10-20 07:00:00');

    Notification::assertSentToTimes($this->owner, DueReminder::class, 1);

    $notification = new DueReminder($this->request->fresh(), true);
    expect($notification->via($this->owner))->toBe(['database', 'mail'])
        ->and($notification->via($this->dean))->toBe(['database'])
        ->and($notification->toMail($this->owner)->actionUrl)->toBe(route('student.requests.show', $this->request));
});

test('REQ-06-14 reminders run at 07:00, skip non-working days and never repeat', function () {
    remindAt('2026-10-18 07:00:00');   // Sunday
    Notification::assertNothingSent();

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:send-due-reminders'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 7 * * *');

    remindAt('2026-10-19 07:00:00');
    remindAt('2026-10-19 09:00:00');
    expect(DB::table('reminder_logs')->count())->toBe(2);   // the Dean's visit and the requester
});
