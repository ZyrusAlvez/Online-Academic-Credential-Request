<?php

use App\Enums\NonWorkingDayKind;
use App\Livewire\Admin\Calendar\Index;
use App\Models\NonWorkingDay;
use App\Models\User;
use App\Services\WorkingCalendar;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->calendar = app(WorkingCalendar::class);
});

afterEach(fn () => Carbon::setTestNow());

test('REQ-10-01 Monday to Saturday are working days except calendar holidays', function () {
    NonWorkingDay::factory()->create(['date' => '2026-10-12', 'name' => 'Typhoon closure', 'kind' => NonWorkingDayKind::Closure]);

    expect($this->calendar->isWorkingDay(Carbon::parse('2026-10-10')))->toBeTrue()     // Saturday
        ->and($this->calendar->isWorkingDay(Carbon::parse('2026-10-11')))->toBeFalse() // Sunday
        ->and($this->calendar->isWorkingDay(Carbon::parse('2026-10-12')))->toBeFalse() // closure
        ->and($this->calendar->isWorkingDay(Carbon::parse('2026-10-13')))->toBeTrue();
});

test('REQ-10-02 adding working days ends on the last working day, skipping the start date', function () {
    // Friday 10:00 + 3 working days = Saturday, Monday, Tuesday
    expect($this->calendar->addWorkingDays(Carbon::parse('2026-10-09 10:00'), 3)->toDateTimeString())
        ->toBe('2026-10-13 23:59:59');

    NonWorkingDay::factory()->create(['date' => '2026-10-12']);

    expect($this->calendar->addWorkingDays(Carbon::parse('2026-10-09 10:00'), 3)->toDateTimeString())
        ->toBe('2026-10-14 23:59:59');
});

test('REQ-10-03 working days between two moments exclude the first day and include the last', function () {
    // The client's example: received Friday Oct 23, released Tuesday Oct 27.
    $received = Carbon::parse('2026-10-23 09:00');
    $released = Carbon::parse('2026-10-27 15:00');

    expect($this->calendar->workingDaysBetween($received, $released))->toBe(3)       // Sat, Mon, Tue
        ->and($this->calendar->workingDaysBetween($received, $received->copy()->setTime(17, 0)))->toBe(0)
        ->and($this->calendar->workingDaysBetween($released, $received))->toBe(0);

    config(['uphsl.work_week' => [1, 2, 3, 4, 5]]);

    expect($this->calendar->workingDaysBetween($received, $released))->toBe(2);    // as in the document
});

test('REQ-10-04 the due date counts processing days on the working calendar', function () {
    $org = organisation();
    $org->free->update(['processing_days' => 3]);
    NonWorkingDay::factory()->create(['date' => '2026-10-12']);
    $request = submitRequest(requester($org->ccs), $org->free);

    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($request, User::factory()->registrar()->create());

    expect($request->fresh()->due_at->toDateTimeString())->toBe('2026-10-14 23:59:59');
});

test('REQ-10-05 calendar changes apply to new due dates only', function () {
    $org = organisation();
    $org->free->update(['processing_days' => 3]);
    $registrar = User::factory()->registrar()->create();
    $owner = requester($org->ccs);
    $first = submitRequest($owner, $org->free);
    $second = submitRequest($owner, $org->free);

    Carbon::setTestNow('2026-10-09 10:00:00');
    acceptRequest($first, $registrar);

    NonWorkingDay::factory()->create(['date' => '2026-10-13']);
    acceptRequest($second, $registrar);

    expect($first->fresh()->due_at->toDateTimeString())->toBe('2026-10-13 23:59:59')
        ->and($second->fresh()->due_at->toDateTimeString())->toBe('2026-10-14 23:59:59');
});

test('REQ-10-06 a date can appear only once', function () {
    NonWorkingDay::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas Day']);

    Livewire::actingAs(User::factory()->admin()->create())->test(Index::class)
        ->call('create')
        ->set('form.date', '2026-12-25')
        ->set('form.name', 'Duplicate')
        ->call('save')
        ->assertHasErrors(['form.date' => 'unique']);
});

test('REQ-10-09 a period adds each working day not yet in the calendar, with the same name and kind', function () {
    $admin = User::factory()->admin()->create();
    NonWorkingDay::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas Day', 'kind' => 'holiday']);

    // Mon Dec 21 to Sat Dec 26: Christmas Day is kept, the other five days are added; no Sunday.
    Livewire::actingAs($admin)->test(Index::class, ['year' => 2026])
        ->call('create')
        ->assertSeeHtml('data-until-input')
        ->set('form.date', '2026-12-20')
        ->set('form.until', '2026-12-26')
        ->set('form.name', 'Christmas break')
        ->set('form.kind', 'closure')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: 'Christmas break saved: 5 days.');

    $break = NonWorkingDay::where('name', 'Christmas break')->orderBy('date')->get();
    expect($break->map(fn ($day) => $day->date->format('Y-m-d'))->all())->toBe(['2026-12-21', '2026-12-22', '2026-12-23', '2026-12-24', '2026-12-26'])
        ->and($break->every(fn ($day) => $day->kind === NonWorkingDayKind::Closure && $day->created_by === $admin->id))->toBeTrue()
        ->and(NonWorkingDay::whereDate('date', '2026-12-25')->sole()->name)->toBe('Christmas Day');

    // Each day is edited on its own: no Until there.
    Livewire::actingAs($admin)->test(Index::class, ['year' => 2026])
        ->call('edit', $break->first()->id)
        ->assertDontSeeHtml('data-until-input');
});

test('REQ-10-08 only the admin can open the working calendar', function () {
    $office = organisation()->vpf;

    $this->actingAs(User::factory()->student()->create())->get(route('admin.calendar'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff($office)->create())->get(route('admin.calendar'))->assertForbidden();
    $this->actingAs(User::factory()->registrar()->create())->get(route('admin.calendar'))->assertForbidden();
});
