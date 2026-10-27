<?php

use App\Enums\NonWorkingDayKind;
use App\Livewire\Registrar\Calendar\Index;
use App\Models\NonWorkingDay;
use App\Models\User;
use App\Services\WorkingCalendar;
use Database\Seeders\NonWorkingDaySeeder;
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

test('REQ-10-01 the work week is a setting', function () {
    config(['uphsl.work_week' => [1, 2, 3, 4, 5]]);

    expect($this->calendar->isWorkingDay(Carbon::parse('2026-10-10')))->toBeFalse();
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

test('REQ-10-06 the registrar can add, edit and remove non-working days by year', function () {
    $registrar = User::factory()->registrar()->create();
    NonWorkingDay::factory()->create(['date' => '2027-01-01', 'name' => 'Holiday in 2027']);

    $this->actingAs($registrar)->get(route('registrar.calendar', ['year' => 2026]))
        ->assertOk()->assertSee('Working days are Monday to Saturday')->assertDontSee('Holiday in 2027');

    $component = Livewire::actingAs($registrar)->test(Index::class, ['year' => 2026])
        ->call('create')
        ->set('form.date', '2026-11-20')
        ->set('form.name', 'Typhoon closure')
        ->set('form.kind', 'closure')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Typhoon closure');

    $day = NonWorkingDay::whereDate('date', '2026-11-20')->sole();
    expect($day->kind)->toBe(NonWorkingDayKind::Closure)
        ->and($day->created_by)->toBe($registrar->id);

    $component->call('edit', $day->id)
        ->set('form.name', 'Typhoon Ada closure')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Typhoon Ada closure')
        ->call('delete', $day->id)
        ->assertDontSee('Typhoon Ada closure');

    expect(NonWorkingDay::whereDate('date', '2026-11-20')->exists())->toBeFalse();
});

test('REQ-10-06 a date can appear only once', function () {
    NonWorkingDay::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas Day']);

    Livewire::actingAs(User::factory()->registrar()->create())->test(Index::class)
        ->call('create')
        ->set('form.date', '2026-12-25')
        ->set('form.name', 'Duplicate')
        ->call('save')
        ->assertHasErrors(['form.date' => 'unique']);
});

test('REQ-10-07 the seeder adds the Philippine holidays for 2026 and 2027 once', function () {
    $this->seed(NonWorkingDaySeeder::class);
    $this->seed(NonWorkingDaySeeder::class);

    $names = NonWorkingDay::pluck('name', 'date')->mapWithKeys(fn ($name, $date) => [substr($date, 0, 10) => $name]);

    expect(NonWorkingDay::count())->toBe(32)
        ->and($names['2026-08-31'])->toBe('National Heroes Day')
        ->and($names['2027-08-30'])->toBe('National Heroes Day')
        ->and($names['2026-04-03'])->toBe('Good Friday')
        ->and($names['2027-12-30'])->toBe('Rizal Day');
});

test('REQ-10-08 only the registrar can open the working calendar', function () {
    $office = organisation()->vpf;

    $this->actingAs(User::factory()->student()->create())->get(route('registrar.calendar'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff($office)->create())->get(route('registrar.calendar'))->assertForbidden();
});
