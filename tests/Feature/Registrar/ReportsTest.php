<?php

use App\Enums\AcademicStatus;
use App\Enums\DelayCategory;
use App\Enums\ReportKind;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Livewire\Registrar\Reports\Index;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\RequestStep;
use App\Models\User;
use App\Reports\ComplianceReport;
use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\EvaluatorReport;
use App\Reports\PerformanceMetrics;
use App\Reports\PeriodicReport;
use App\Reports\ReleaseReport;
use App\Reports\Report;
use App\Reports\ReportFilter;
use App\Reports\SignatoryReport;
use App\Reports\TrackingReport;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
| One hand-computed month (October 2026; "now" is Tue Oct 20, 12:00):
|
|   A  submitted Oct 2, accepted Oct 2, due Oct 10, released Oct 9  → on time, 6 working days
|   B  submitted Oct 3, accepted Oct 3, due Oct 12, released Oct 15 → late, 10 working days
|   C  submitted Oct 5, accepted Oct 5, due Oct 14, still at the Chancellor → overdue (open)
|   D  submitted Oct 10, accepted Oct 10, due Oct 25 → not yet due: not eligible
|   E  submitted Sep 20, released Sep 29 → outside the period
|   F  submitted Oct 6, accepted Oct 6, due Oct 12, cancelled Oct 7 → excluded
|   G  submitted Oct 18, not evaluated yet
|   H  submitted Oct 19 08:00, evaluation started 14:00, pending compliance (Form 137)
|   R  accepted Sep 2, ready for release since Oct 1 → unclaimed
|
| Eligible = A, B, C → on time 1 (33.3%), overdue 2 (66.7%). Received in October = 7.
*/

beforeEach(function () {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $this->org = organisation();
    $this->owner = requester($this->org->ccs);
    $this->registrar = User::factory()->registrar()->create(['first_name' => 'Eva', 'middle_name' => null, 'last_name' => 'Luator']);
    $this->filter = new ReportFilter('2026-10-01', '2026-10-31');

    $at = fn (?string $date) => $date === null ? null : Carbon::parse($date);
    $make = function (string $name, string $status, array $dates, array $extra = []) use ($at): CredentialRequest {
        return CredentialRequest::factory()->create([
            'user_id' => $this->owner->id,
            'credential_type_id' => $this->org->tor->id,
            'college_id' => $this->org->ccs->id,
            'academic_status' => AcademicStatus::Graduate,
            'purpose' => $name,
            'status' => RequestStatus::from($status),
            'submitted_at' => $at($dates['submitted'] ?? null),
            'accepted_at' => $at($dates['accepted'] ?? null),
            'due_at' => $at($dates['due'] ?? null),
            'ready_at' => $at($dates['ready'] ?? null),
            'released_at' => $at($dates['released'] ?? null),
            'cancelled_at' => $at($dates['cancelled'] ?? null),
        ] + $extra);
    };

    $this->a = $make('A', 'released', ['submitted' => '2026-10-02 08:00', 'accepted' => '2026-10-02 09:00', 'due' => '2026-10-10 23:59:59', 'ready' => '2026-10-08 10:00', 'released' => '2026-10-09 10:00'],
        ['processing_working_days' => 6, 'evaluation_started_at' => '2026-10-02 10:00', 'evaluated_by' => $this->registrar->id, 'requirements_completed_at' => '2026-10-02 11:00']);
    $this->b = $make('B', 'released', ['submitted' => '2026-10-03 08:00', 'accepted' => '2026-10-03 09:00', 'due' => '2026-10-12 23:59:59', 'ready' => '2026-10-14 10:00', 'released' => '2026-10-15 10:00'],
        ['processing_working_days' => 10, 'evaluation_started_at' => '2026-10-03 12:00', 'evaluated_by' => $this->registrar->id, 'requirements_completed_at' => '2026-10-03 13:00']);
    $this->c = $make('C', 'in_progress', ['submitted' => '2026-10-05 08:00', 'accepted' => '2026-10-05 09:00', 'due' => '2026-10-14 23:59:59']);
    $this->d = $make('D', 'in_progress', ['submitted' => '2026-10-10 08:00', 'accepted' => '2026-10-10 09:00', 'due' => '2026-10-25 23:59:59']);
    $make('E', 'released', ['submitted' => '2026-09-20 08:00', 'accepted' => '2026-09-20 09:00', 'due' => '2026-09-30 23:59:59', 'released' => '2026-09-29 10:00'], ['processing_working_days' => 7]);
    $make('F', 'cancelled', ['submitted' => '2026-10-06 08:00', 'accepted' => '2026-10-06 09:00', 'due' => '2026-10-12 23:59:59', 'cancelled' => '2026-10-07 10:00']);
    $this->g = $make('G', 'submitted', ['submitted' => '2026-10-18 08:00']);
    $this->h = $make('H', 'pending_compliance', ['submitted' => '2026-10-19 08:00'], ['evaluation_started_at' => '2026-10-19 14:00', 'evaluated_by' => $this->registrar->id]);
    $this->h->deficiencies()->make(['description' => 'Original Form 137'])->forceFill(['recorded_by' => $this->registrar->id])->save();
    $this->r = $make('R', 'ready_for_release', ['submitted' => '2026-09-01 08:00', 'accepted' => '2026-09-02 09:00', 'due' => '2026-09-11 23:59:59', 'ready' => '2026-10-01 10:00']);

    $visit = function (CredentialRequest $request, Department $office, string $status, ?string $received, ?string $completed, ?int $days, array $extra = []): OfficeVisit {
        $step = RequestStep::forceCreate(['credential_request_id' => $request->id, 'position' => $request->steps()->count() + 1, 'department_id' => $office->id,
            'task' => RouteTask::Sign, 'status' => StepStatus::Completed]);
        $visit = new OfficeVisit;
        $visit->forceFill($extra + [
            'credential_request_id' => $request->id, 'request_step_id' => $step->id, 'department_id' => $office->id, 'visit_no' => 1,
            'purpose' => VisitPurpose::Route, 'status' => VisitStatus::from($status), 'forwarded_at' => $received ?? '2026-10-14 08:00', 'forwarded_by' => $this->registrar->id,
            'received_at' => $received, 'received_by' => $received ? $this->registrar->id : null,
            'completed_at' => $completed, 'completed_by' => $completed ? $this->registrar->id : null, 'working_days' => $days,
        ])->save();

        return $visit;
    };

    // A's route: REG 1 day, Dean 3 days, VP Finance returned after 2 days.
    $visit($this->a, $this->org->registrarOffice, 'completed', '2026-10-02 09:30', '2026-10-03 09:00', 1);
    $visit($this->a, $this->org->ccs, 'completed', '2026-10-03 10:00', '2026-10-06 10:00', 3);
    $visit($this->a, $this->org->vpf, 'returned', '2026-10-06 11:00', '2026-10-08 09:00', 2, ['returned_to_department_id' => $this->org->ccs->id, 'return_reason' => 'Unsigned page']);
    // B stayed 5 days at VP Finance (September visit dates are outside the period's office times).
    $visit($this->b, $this->org->vpf, 'completed', '2026-09-28 09:00', '2026-09-30 09:00', 5);
    // C is at the Chancellor since Wed Oct 14, with a recorded delay reason.
    $visit($this->c, $this->org->chancellor, 'received', '2026-10-14 09:00', null, null);
    $this->c->delayNotes()->create(['department_id' => $this->org->chancellor->id, 'category' => DelayCategory::PendingSignature,
        'reason' => 'The Chancellor is abroad', 'corrective_action' => 'Sign on return', 'recorded_by' => $this->registrar->id]);
});

afterEach(fn () => Carbon::setTestNow());

/** @return list<list<mixed>> */
function reportRows(Report $report, ?ReportFilter $filter = null): array
{
    return collect($report->rows($filter ?? test()->filter))->values()->all();
}

test('REQ-07-04 the indicators of a period', function () {
    $metrics = PerformanceMetrics::for($this->filter);

    expect($metrics)
        ->received->toBe(7)
        ->eligible->toBe(3)
        ->onTime->toBe(1)
        ->overdue->toBe(2)
        ->avgProcessingDays->toBe(8.0)
        ->overdueWorkload->toBe(1)
        ->returns->toBe(1)
        ->avgInitialEvaluationHours->toBe(4.0)   // A 2 h, B 4 h, H 6 h
        ->and($metrics->onTimeRate())->toBe(33.3)
        ->and($metrics->overdueRate())->toBe(66.7)
        ->and($metrics->returnRate())->toBe(33.3);

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Total requests', '7', 'Released on time', '1', '33.3%', 'Overdue', '2', '66.7%', 'Average processing time', '8 working days']);
});

test('REQ-07-11 requests within their period are not overdue; open past-due ones are overdue workload', function () {
    $toDate = PerformanceMetrics::for(new ReportFilter('2026-10-01', '2026-10-13'));   // C (due 14th) not yet judged

    expect($toDate)->eligible->toBe(2)->onTime->toBe(1)->overdue->toBe(1)
        ->and(PerformanceMetrics::for($this->filter)->overdueWorkload)->toBe(1)   // C only; D is not due yet
        ->and(PerformanceMetrics::for(new ReportFilter('2026-11-01', '2026-11-30')))->eligible->toBe(0)
        ->and(PerformanceMetrics::for(new ReportFilter('2026-11-01', '2026-11-30'))->onTimeRate())->toBeNull();
});

test('REQ-07-12 the average working days per office, longest first', function () {
    $metrics = PerformanceMetrics::for($this->filter);

    expect($metrics->avgOfficeDays)->toBe(2.0)
        ->and($metrics->byOffice->map(fn ($row) => [$row['name'], $row['days']])->all())->toBe([
            ['College of Computer Studies', 3.0], ['Office of the VP for Finance', 2.0], ['Office of the Registrar', 1.0],
        ]);

    $this->actingAs($this->registrar)->get(route('registrar.dashboard'))
        ->assertSeeInOrder(['Processing time by office', 'College of Computer Studies', '3 working days', 'Office of the VP for Finance', '2 working days']);
});

test('REQ-07-13 the ten reports render with their filters, for the registrar only', function () {
    foreach (ReportKind::cases() as $kind) {
        $this->actingAs($this->registrar)->get(route('registrar.reports', ['report' => $kind->value, 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee($kind->report()->title());
    }

    $other = CredentialType::factory()->create();
    $byType = new ReportFilter('2026-10-01', '2026-10-31', type: (string) $other->id);
    $byOffice = new ReportFilter('2026-10-01', '2026-10-31', office: (string) $this->org->chancellor->id);
    $byEvaluator = new ReportFilter('2026-10-01', '2026-10-31', evaluator: (string) $this->registrar->id);

    expect(PerformanceMetrics::for($byType)->received)->toBe(0)
        ->and(PerformanceMetrics::for($byOffice)->received)->toBe(1)       // only C visited the Chancellor
        ->and(PerformanceMetrics::for($byEvaluator)->received)->toBe(3);   // A, B, H

    Livewire::actingAs($this->registrar)->test(Index::class)
        ->set('report', 'delay')->set('from', '2026-10-01')->set('to', '2026-10-31')
        ->assertSee($this->c->reference_no)
        ->set('office', (string) $this->org->vpf->id)
        ->assertDontSee($this->c->reference_no);

    $this->actingAs($this->owner)->get(route('registrar.reports'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff($this->org->vpf)->create())->get(route('registrar.reports'))->assertForbidden();
});

test('REQ-07-14 the periodic reports count per day, week and month', function () {
    $month = reportRows(new PeriodicReport('month'));
    expect($month)->toBe([['October 2026', 7, 5, 2, 1, 2, 33.3, 5, 8.0]]);   // pending at the end: C, D, G, H and R (not yet claimed)

    $days = collect(reportRows(new PeriodicReport('day')))->keyBy(0);
    expect($days)->toHaveCount(20)   // Oct 1 – Oct 20 (not after today)
        ->and($days['Fri, Oct 2, 2026'])->toBe(['Fri, Oct 2, 2026', 1, 1, 0, 0, 0, null, 2, null])
        ->and($days['Fri, Oct 9, 2026'][3])->toBe(1);

    expect(collect(reportRows(new PeriodicReport('week')))->pluck(0)->first())->toBe('Week of Sep 28, 2026');
});

test('REQ-07-15 the evaluator report', function () {
    expect(reportRows(new EvaluatorReport))->toBe([['Eva Luator', 3, 2, 1, 0, 4.0, 1]]);
});

test('REQ-07-16 the department and signatory monitoring reports', function () {
    expect(reportRows(new DepartmentReport))->toBe([
        ['College of Computer Studies', 1, 1, 0, 0, 3.0],
        ['Office of the VP for Finance', 1, 0, 1, 0, 2.0],
        ['Office of the Registrar', 1, 1, 0, 0, 1.0],
        ['Office of the Chancellor', 1, 0, 0, 1, null],
    ]);

    expect(reportRows(new SignatoryReport))->toBe([[
        'Office of the Chancellor', $this->c->reference_no, 'Transcript of Records', 'Sign', 'In office',
        '2026-10-14 09:00', 5, '2026-10-14', 'Yes',   // Thu, Fri, Sat, Mon, Tue
    ]]);
});

test('REQ-07-17 the credential tracking report lists every visit of requests accepted in the period', function () {
    $rows = reportRows(new TrackingReport);

    expect($rows)->toHaveCount(5)   // A's three visits, B's and C's
        ->and(collect($rows)->pluck(0)->unique()->values()->all())->toBe([$this->a->reference_no, $this->b->reference_no, $this->c->reference_no])
        ->and($rows[2][8])->toBe('Returned for correction')
        ->and($rows[2][9])->toBe('To College of Computer Studies: Unsigned page');
});

test('REQ-07-18 the delay analysis separates the holding office from the cause', function () {
    $rows = collect(reportRows(new DelayReport))->keyBy(0);

    expect($rows)->toHaveCount(2)
        ->and($rows[$this->b->reference_no])->toBe([
            $this->b->reference_no, 'Transcript of Records', '2026-10-03', '2026-10-12', '2026-10-15', '—', 'Released',
            3, 0, 'VP Finance documentary stamp', null, null,   // late Tue–Thu; inferred from the slowest office
        ])
        ->and($rows[$this->c->reference_no])->toBe([
            $this->c->reference_no, 'Transcript of Records', '2026-10-05', '2026-10-14', null, 'Office of the Chancellor', 'In progress',
            5, 0, 'Pending signature', 'The Chancellor is abroad', 'Sign on return',
        ]);
});

test('REQ-07-19 the customer compliance and release reports', function () {
    expect(reportRows(new ComplianceReport))->toBe([[
        $this->h->reference_no, $this->owner->name, $this->owner->contact_number, 'Transcript of Records', 'Pending compliance',
        'Missing: Original Form 137', '2026-10-20', 0,   // no ledger entry in this fixture: since its last update
    ]]);

    $release = collect(reportRows(new ReleaseReport))->keyBy(0);
    expect($release[$this->r->reference_no][3])->toBe('Unclaimed')
        ->and($release[$this->r->reference_no][7])->toBe(16)
        ->and($release[$this->a->reference_no][3])->toBe('Released');
});

test('REQ-07-20 reports download as formula-safe CSV for the registrar only', function () {
    $this->c->forceFill(['purpose' => 'x'])->save();
    $this->owner->forceFill(['first_name' => '=cmd'])->save();

    $response = $this->actingAs($this->registrar)->get(route('registrar.reports.export', ['report' => 'compliance']));
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('"Tracking no.",Requester,Contact,Credential')
        ->toContain($this->h->reference_no)
        ->toContain("'=cmd");

    $this->actingAs($this->registrar)->get(route('registrar.reports.export', ['report' => 'nope']))->assertNotFound();
    $this->actingAs($this->owner)->get(route('registrar.reports.export', ['report' => 'delay']))->assertForbidden();
});
