<?php

use App\Actions\Workflow\SetAllottedDays;
use App\Enums\VisitPurpose;
use App\Livewire\Admin\CredentialTypes\Index;
use App\Livewire\Admin\CredentialTypes\Workflow;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\User;
use App\Notifications\DueReminder;
use App\Reports\DepartmentReport;
use App\Reports\ReportFilter;
use Database\Seeders\CredentialTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return list<int>
 */
function allotted(CredentialType $type): array
{
    return $type->steps()->pluck('allotted_days')->all();
}

function remindOfficesAt(string $time): void
{
    Carbon::setTestNow($time);
    test()->artisan('app:send-due-reminders')->assertSuccessful();
}

/**
 * A TOR accepted on Friday, Oct 9, 2026 (7 working days: Registrar 2, Dean 2, VP Finance 2,
 * Chancellor 1), passed on to the Dean.
 */
function torAtDean(object $test): object
{
    $test->org = organisation();
    $test->owner = requester($test->org->ccs);
    $test->registrar = User::factory()->registrar()->create();
    $test->dean = User::factory()->departmentStaff($test->org->ccs)->create();
    $test->vpf = User::factory()->departmentStaff($test->org->vpf)->create();

    Carbon::setTestNow('2026-10-09 10:00:00');   // Friday
    $test->request = acceptRequest(submitRequest($test->owner, $test->org->tor), $test->registrar);
    $test->registrarVisit = openVisit($test->request);
    passOffice($test->request, $test->registrar);

    return $test;
}

test('REQ-30-01 REQ-30-02 the seeded routes split each processing period among their offices', function () {
    $this->seed([DepartmentSeeder::class, CredentialTypeSeeder::class]);

    $split = fn (string $name): array => allotted(CredentialType::where('name', $name)->sole());

    expect($split('Transcript of Records (TOR)'))->toBe([2, 2, 2, 1])
        ->and($split('TOR for Transfer Credentials'))->toBe([2, 2, 2, 1])
        ->and($split('Certification of Graduation'))->toBe([2, 2, 2])
        ->and($split('Diploma / Certificate for Non-Degree Programs'))->toBe([2, 2, 2])
        ->and($split('Authentication'))->toBe([1])
        ->and($split('Certification, Authentication, Verification (CAV)'))->toBe([2]);

    CredentialType::with('steps')->get()->each(
        fn (CredentialType $type) => expect($type->steps->sum('allotted_days'))->toBe($type->processing_days, $type->name)
    );
});

test('REQ-30-02 the split: the period over the offices, the days left over to the first offices', function () {
    expect(CredentialType::splitDays(7, 4))->toBe([2, 2, 2, 1])
        ->and(CredentialType::splitDays(6, 3))->toBe([2, 2, 2])
        ->and(CredentialType::splitDays(10, 4))->toBe([3, 3, 2, 2])
        ->and(CredentialType::splitDays(1, 2))->toBe([1, 0])
        ->and(CredentialType::splitDays(5, 0))->toBe([]);
});

test('REQ-30-02 adding or removing an office or changing the period splits again; moving keeps the days', function () {
    $library = Department::factory()->create(['name' => 'Library', 'code' => 'LIB']);
    $cashier = Department::factory()->create(['name' => 'Cashier', 'code' => 'FIN']);
    $registrarOffice = Department::factory()->registrarOffice()->create();
    $type = CredentialType::factory()->create(['processing_days' => 5]);

    $page = Livewire::actingAs($this->admin)->test(Workflow::class, ['credentialType' => $type])
        ->set('newStep', (string) $registrarOffice->id)->call('addStep')
        ->set('newStep', (string) $library->id)->call('addStep');
    expect(allotted($type))->toBe([3, 2])
        ->and($page->get('days'))->toBe(array_combine($type->steps()->pluck('id')->all(), [3, 2]));

    $page->set('newStep', (string) $cashier->id)->call('addStep');
    expect(allotted($type))->toBe([2, 2, 1]);

    [$first, , $last] = $type->steps()->get()->all();
    app(SetAllottedDays::class)->handle($type, [$first->id => 1, $type->steps()->get()[1]->id => 1, $last->id => 3]);
    $page->call('move', $last->id, -1);
    expect($type->steps()->pluck('allotted_days', 'department_id')->all())
        ->toBe([$registrarOffice->id => 1, $cashier->id => 3, $library->id => 1]);

    $page->call('remove', $last->id);
    expect(allotted($type))->toBe([3, 2]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $type->id)->set('form.processing_days', 9)->call('save')->assertHasNoErrors();
    expect(allotted($type))->toBe([5, 4]);

    // Saving without changing the period keeps the Admin's split.
    app(SetAllottedDays::class)->handle($type, array_combine($type->steps()->pluck('id')->all(), [2, 7]));
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $type->id)->set('form.name', 'Renamed')->call('save')->assertHasNoErrors();
    expect(allotted($type))->toBe([2, 7]);
});

test('REQ-30-03 the Admin sets each office\'s days on the route page; the total must be the processing period', function () {
    $org = organisation();
    $ids = $org->tor->steps()->pluck('id')->all();

    $page = Livewire::actingAs($this->admin)->test(Workflow::class, ['credentialType' => $org->tor])
        ->assertSee('Working Days for Each Office')
        ->assertSee('Total 7 of 7 working days');

    $page->set('days', array_combine($ids, [3, 2, 1, 1]))->call('saveDays')->assertHasNoErrors();
    expect(allotted($org->tor))->toBe([3, 2, 1, 1]);

    $page->set('days', array_combine($ids, [3, 3, 1, 2]))->call('saveDays')->assertHasErrors('days')
        ->assertSee('Total 9 of 7 working days');
    $page->set('days', array_combine($ids, [7, 0, 0, 0]))->call('saveDays')->assertHasNoErrors();
    $page->set('days', array_combine($ids, [61, -1, 0, 0]))->call('saveDays')->assertHasErrors('days');
    $page->set('days', array_combine($ids, ['two', 2, 2, 1]))->call('saveDays')->assertHasErrors('days');

    expect(allotted($org->tor))->toBe([7, 0, 0, 0]);

    $staff = User::factory()->registrar()->create();
    Livewire::actingAs($staff)->test(Workflow::class, ['credentialType' => $org->tor])->assertForbidden();
});

test('REQ-30-04 a request keeps the days its route had at acceptance; the final check has none', function () {
    torAtDean($this);

    $this->org->tor->steps()->update(['allotted_days' => 5]);

    expect($this->request->steps()->orderBy('position')->pluck('allotted_days')->all())->toBe([2, 2, 2, 1, null]);
});

test('REQ-30-05 each office\'s deadline counts its working days from its own receipt', function () {
    torAtDean($this);

    // Received at acceptance on Friday; 2 working days: Saturday, (Sunday off) Monday.
    expect($this->registrarVisit->fresh()->due_at->toDateTimeString())->toBe('2026-10-12 23:59:59');

    Carbon::setTestNow('2026-10-10 09:00:00');   // the Dean receives on Saturday
    workflow()->acceptReceipt($visit = openVisit($this->request), $this->dean);

    expect($visit->fresh()->due_at->toDateTimeString())->toBe('2026-10-13 23:59:59')
        ->and($this->request->fresh()->due_at->toDateString())->toBe('2026-10-17');   // the request's due date is unchanged
});

test('REQ-30-05 REQ-30-06 a document received again gets a new deadline; a correction and the final check have none', function () {
    torAtDean($this);

    Carbon::setTestNow('2026-10-10 09:00:00');
    workflow()->acceptReceipt($dean = openVisit($this->request), $this->dean);
    workflow()->returnVisit($dean, $this->dean, $this->org->registrarOffice, 'Wrong middle name');

    Carbon::setTestNow('2026-10-12 09:00:00');   // Monday
    $correction = openVisit($this->request);
    workflow()->acceptReceipt($correction, $this->registrar);
    expect($correction->fresh()->purpose)->toBe(VisitPurpose::Correction)
        ->and($correction->fresh()->due_at)->toBeNull();

    workflow()->completeVisit($correction, $this->registrar);
    workflow()->forwardDocument($correction->fresh(), $this->registrar);

    Carbon::setTestNow('2026-10-13 09:00:00');   // Tuesday: the corrected document back at the Dean
    workflow()->acceptReceipt($again = openVisit($this->request), $this->dean);
    expect($again->fresh()->due_at->toDateTimeString())->toBe('2026-10-15 23:59:59')
        ->and($dean->fresh()->due_at->toDateTimeString())->toBe('2026-10-13 23:59:59');   // the first visit keeps its own

    passOffice($this->request, $this->dean);
    passOffice($this->request, $this->vpf);
    passOffice($this->request, User::factory()->departmentStaff($this->org->chancellor)->create());
    workflow()->acceptReceipt($check = openVisit($this->request), $this->registrar);

    expect($check->fresh()->due_at)->toBeNull()
        ->and(OfficeVisit::where('credential_request_id', $this->request->id)->whereNull('due_at')->whereNotNull('received_at')->count())->toBe(2);
});

test('REQ-30-07 the office queue shows the office deadline and Overdue once it has passed', function () {
    torAtDean($this);

    Carbon::setTestNow('2026-10-10 09:00:00');
    workflow()->acceptReceipt(openVisit($this->request), $this->dean);

    $this->actingAs($this->dean)->get(route('department.to-validate'))
        ->assertOk()
        ->assertSee('Due Tue, Oct 13')
        ->assertDontSee('Overdue');

    Carbon::setTestNow('2026-10-14 09:00:00');   // past the Dean's deadline, not the request's (Oct 17)
    $this->actingAs($this->dean)->get(route('department.to-validate'))
        ->assertSee('Overdue');
});

test('REQ-30-08 an office is reminded by its own deadline; registrars and the requester by the request\'s', function () {
    torAtDean($this);

    Carbon::setTestNow('2026-10-10 09:00:00');
    workflow()->acceptReceipt(openVisit($this->request), $this->dean);   // due Tue, Oct 13
    Notification::fake();

    remindOfficesAt('2026-10-12 07:00:00');   // Monday: due the next working day
    remindOfficesAt('2026-10-12 08:00:00');   // rerun

    Notification::assertSentToTimes($this->dean, DueReminder::class, 1);
    Notification::assertSentTo($this->dean, DueReminder::class, fn (DueReminder $n) => ! $n->overdue
        && $n->toArray($this->dean)['message'] === 'It is with your office and due Tuesday, Oct 13.');
    Notification::assertNotSentTo($this->registrar, DueReminder::class);   // the request is due Oct 17

    remindOfficesAt('2026-10-14 07:00:00');   // Wednesday: past the Dean's deadline
    remindOfficesAt('2026-10-15 07:00:00');

    Notification::assertSentToTimes($this->dean, DueReminder::class, 2);
    Notification::assertSentTo($this->dean, DueReminder::class, fn (DueReminder $n) => $n->overdue);
    Notification::assertNotSentTo($this->owner, DueReminder::class);

    remindOfficesAt('2026-10-16 07:00:00');   // Friday: the request is due the next working day
    Notification::assertSentToTimes($this->registrar, DueReminder::class, 1);
    Notification::assertSentToTimes($this->dean, DueReminder::class, 2);   // not again for the request's date
});

test('REQ-30-09 the department report shows the share of documents done by the office deadline', function () {
    torAtDean($this);

    Carbon::setTestNow('2026-10-10 09:00:00');
    passOffice($this->request, $this->dean);   // received Saturday, done the same day: on time

    Carbon::setTestNow('2026-10-12 09:00:00');
    workflow()->acceptReceipt($vpf = openVisit($this->request), $this->vpf);   // due Wed, Oct 14
    Carbon::setTestNow('2026-10-16 09:00:00');
    passOffice($this->request, $this->vpf);   // done Friday: late

    $rows = collect((new DepartmentReport)->rows(new ReportFilter))->keyBy(0);

    expect((new DepartmentReport)->columns())->toContain('On time %')
        ->and($rows[$this->org->ccs->name][6])->toBe(100.0)
        ->and($rows[$this->org->vpf->name][6])->toBe(0.0)
        ->and($rows[$this->org->registrarOffice->name][6])->toBe(100.0);
});
