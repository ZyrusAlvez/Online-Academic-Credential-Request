<?php

use App\Enums\AcademicStatus;
use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\RequestEvent;
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Services\WorkingCalendar;
use Database\Seeders\DemoRequestSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Spec 31: the demo data app:reset-demo loads, generated through the real workflow. Kept small here
| (phpunit.xml); the servers use the defaults.
*/

beforeEach(fn () => Storage::fake(RequestWorkflow::proofDisk()));

/** Accounts made for the data, not the ones listed in credentials.md. */
function generatedRequesters(): Collection
{
    $listed = array_column(DemoSeeder::REQUESTERS, 0);

    return User::where('role', Role::Student)->whereNotIn('email', $listed)->where('email', 'not like', 'walkin.%')->get();
}

test('REQ-31-02 generated requesters cover every college, both sexes and every status, each with a profile and an ID', function () {
    config(['uphsl.demo.requesters' => 40, 'uphsl.demo.applications' => 0]);
    $this->seed(DemoRequestSeeder::class);

    $requesters = generatedRequesters();
    $colleges = Department::colleges()->active()->has('programs')->pluck('id');

    expect($requesters)->toHaveCount(40)
        ->and($requesters->every(fn (User $user): bool => str_ends_with($user->email, '@example.com') && $user->hasRequesterDetails() && $user->latestIdentityDocument()->exists()))->toBeTrue()
        ->and($requesters->pluck('college_id')->unique()->sort()->values()->all())->toBe($colleges->sort()->values()->all())
        ->and($requesters->pluck('sex')->unique()->sortBy('value')->values()->all())->toBe([Sex::Female, Sex::Male])
        ->and($requesters->pluck('academic_status')->map->value->unique()->sort()->values()->all())->toBe(collect(AcademicStatus::cases())->map->value->sort()->values()->all())
        ->and($requesters->whereNotNull('maiden_name')->every(fn (User $user): bool => $user->sex === Sex::Female))->toBeTrue()
        ->and(User::whereIn('email', ['registrar2@example.com', 'registrar3@example.com'])->where('role', Role::Registrar)->count())->toBe(2);
});

test('REQ-31-03 generated applications span the past months, mostly finished when older, with walk-ins and several credentials', function () {
    config(['uphsl.demo.requesters' => 20, 'uphsl.demo.applications' => 60]);
    $this->seed(DemoRequestSeeder::class);

    $generated = CredentialRequest::whereIn('user_id', generatedRequesters()->pluck('id'))->get();
    $old = $generated->filter(fn (CredentialRequest $request): bool => $request->submitted_at->lt(now()->subDays(30)));

    expect($generated->count())->toBeGreaterThan(80)
        ->and($generated->min('submitted_at')->lt(now()->subDays(60)))->toBeTrue()
        ->and($old->where('status', RequestStatus::Released)->count())->toBeGreaterThan($old->count() / 2)
        ->and(CredentialRequest::where('channel', RequestChannel::WalkIn)->count())->toBeGreaterThan(2)
        ->and(CredentialRequest::where('item_no', 2)->count())->toBeGreaterThan(1)
        ->and(CredentialRequest::whereNotNull('academic_year')->exists())->toBeTrue()
        ->and(DB::table('request_checks')->distinct()->pluck('checked_by')->count())->toBeGreaterThanOrEqual(2);
});

test('REQ-31-04 there is a request in every status and documents received at and on their way to every office', function () {
    $this->seed(DemoRequestSeeder::class);

    $open = OfficeVisit::whereIn('status', [VisitStatus::Received, VisitStatus::InTransit])->get();

    expect(CredentialRequest::distinct()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(collect(RequestStatus::cases())->reject(fn ($s) => $s === RequestStatus::ReturnedForCustomerAction)->map->value->sort()->values()->all())
        ->and(CredentialRequest::overdue()->count())->toBeGreaterThanOrEqual(3);

    foreach (Department::active()->get() as $office) {
        // The Registrar carries every paper, so its own office receives at once (REQ-32-09).
        $expected = $office->code === Department::REGISTRAR_CODE ? 1 : 2;
        expect($open->where('department_id', $office->id)->pluck('status')->unique()->count())->toBe($expected, "{$office->code} holds no received or incoming document");
    }
});

/** The working day $offset working days from $day (0: that day, or the next working day). */
function workingDayFrom(WorkingCalendar $calendar, Carbon $day, int $offset): Carbon
{
    $day = $day->copy()->startOfDay();

    if ($offset === 0) {
        while (! $calendar->isWorkingDay($day)) {
            $day->addDay();
        }

        return $day;
    }

    for ($left = abs($offset); $left > 0;) {
        $day->addDays($offset < 0 ? -1 : 1);
        $left -= $calendar->isWorkingDay($day) ? 1 : 0;
    }

    return $day;
}

test('REQ-31-06 REQ-31-07 dated as of a chosen moment, with due dates around that day and new applications that morning', function () {
    $asOf = now()->subWeek()->setTime(8, 30);
    config(['uphsl.demo.as_of' => $asOf->toDateTimeString()]);
    $this->seed(DemoRequestSeeder::class);

    // Nothing after the as-of moment (REQ-31-05, 06).
    expect(RequestEvent::max('created_at'))->not->toBeGreaterThan($asOf->toDateTimeString())
        ->and(CredentialRequest::whereDate('submitted_at', $asOf->toDateString())->count())->toBeGreaterThanOrEqual(5);

    // Two credentials at an office for each due date around the day (REQ-31-07).
    $calendar = app(WorkingCalendar::class);
    foreach ([-5, -3, -1, 0, 1, 2] as $offset) {
        $due = workingDayFrom($calendar, $asOf, $offset);
        expect(CredentialRequest::whereIn('status', RequestStatus::processing())->whereDate('effective_due_at', $due->toDateString())->count())
            ->toBeGreaterThanOrEqual(2, "nothing due on {$due->toDateString()} (offset {$offset})");
    }
});

test('REQ-31-06 an as-of moment that cannot be read stops app:reset-demo before anything is wiped', function () {
    app()['env'] = 'local';
    $kept = User::factory()->create();

    $this->artisan('app:reset-demo', ['--as-of' => 'next presentation'])
        ->expectsOutputToContain('Cannot read the as-of moment')
        ->assertFailed();

    expect(User::find($kept->id))->not->toBeNull();
});

test('REQ-31-05 the generated history notifies no one and is never dated after the moment it is loaded', function () {
    config(['uphsl.demo.applications' => 20]);
    $before = now();
    $this->seed(DemoRequestSeeder::class);

    $generated = generatedRequesters()->pluck('id');

    expect(DB::table('notifications')->whereIn('notifiable_id', $generated)->count())->toBe(0)
        ->and(RequestEvent::where('created_at', '>', now())->count())->toBe(0)
        ->and(CredentialRequest::max('submitted_at'))->not->toBeGreaterThan(now()->toDateTimeString())
        ->and(now()->greaterThanOrEqualTo($before))->toBeTrue();
});
