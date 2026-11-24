<?php

use App\Enums\AcademicStatus;
use App\Enums\DelayCategory;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Livewire\Admin\Reports\Index;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\RequestStep;
use App\Models\User;
use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\EvaluatorReport;
use App\Reports\MonthlySummaryReport;
use App\Reports\PerformanceMetrics;
use App\Reports\Report;
use App\Reports\ReportFilter;
use App\Reports\TransactionReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
| One hand-computed month (October 2026; "now" is Tue Oct 20, 12:00):
|
| On time and late are judged when the credential is ready for release (REQ-34-11).
|
|   A  submitted Oct 2, accepted Oct 2, due Oct 10, ready Oct 8, released Oct 9  → on time, 6 working days
|   B  submitted Oct 3, accepted Oct 3, due Oct 12, ready Oct 14, released Oct 15 → late, 10 working days
|   C  submitted Oct 5, accepted Oct 5, due Oct 14, still at the Chancellor → overdue (open)
|   D  submitted Oct 10, accepted Oct 10, due Oct 25 → not yet due: not eligible
|   E  submitted Sep 20, ready Sep 28, released Sep 29 → outside the period
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
    $this->admin = User::factory()->admin()->create();   // the dashboard and Reports are the Admin's (REQ-18-04)
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
    $make('E', 'released', ['submitted' => '2026-09-20 08:00', 'accepted' => '2026-09-20 09:00', 'due' => '2026-09-30 23:59:59', 'ready' => '2026-09-28 10:00', 'released' => '2026-09-29 10:00'], ['processing_working_days' => 7]);
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

test('REQ-34-01 the three indicators of a period', function () {
    $metrics = PerformanceMetrics::for($this->filter);

    expect($metrics)
        ->received->toBe(7)
        ->eligible->toBe(3)
        ->onTime->toBe(1)
        ->overdue->toBe(2)
        ->and($metrics->onTimeRate())->toBe(33.3)
        ->and($metrics->overdueRate())->toBe(66.7);

    $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];
    $this->actingAs($this->admin)->get(route('admin.dashboard', $period))
        ->assertOk()
        ->assertSeeInOrder(['Total requests', '7', 'Ready on time', '1', '33.3%', 'Overdue', '2', '66.7%'])
        // Total requests and Ready on time open Records; Overdue opens Delay Analysis (REQ-34-17).
        ->assertSee(route('records', ['scope' => 'submitted', ...$period]))
        ->assertSee(route('records', ['scope' => 'on_time', ...$period]))
        ->assertSee(route('admin.reports', [...$period, 'report' => 'delay']))
        ->assertDontSee(route('admin.reports', [...$period, 'report' => 'transactions']));

    // The rows behind Overdue are as many as the tile says.
    expect(reportRows(new DelayReport))->toHaveCount(2);
});

test('REQ-34-09 the dashboard shows the average processing time per credential, longest first', function () {
    $certificate = CredentialType::factory()->create(['name' => 'Certificate of Enrollment', 'processing_days' => 3]);
    CredentialRequest::factory()->create([
        'user_id' => $this->owner->id, 'credential_type_id' => $certificate->id, 'college_id' => $this->org->ccs->id,
        'status' => RequestStatus::Released, 'submitted_at' => '2026-10-15 08:00', 'accepted_at' => '2026-10-15 09:00',
        'due_at' => '2026-10-20 23:59:59', 'ready_at' => '2026-10-16 09:00', 'released_at' => '2026-10-16 10:00', 'processing_working_days' => 1,
    ]);

    // A (6) and B (10) are Transcripts ready in October; E (September) is outside the period.
    expect(PerformanceMetrics::byCredential($this->filter)->all())->toBe([
        ['id' => $this->org->tor->id, 'name' => 'Transcript of Records', 'ready' => 2, 'days' => 8.0, 'standard' => 7],
        ['id' => $certificate->id, 'name' => 'Certificate of Enrollment', 'ready' => 1, 'days' => 1.0, 'standard' => 3],
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertSeeInOrder(['Processing Time by Credential', 'Transcript of Records', '2', '8 working days', '7 working days',
            'Certificate of Enrollment', '1', '1 working day', '3 working days'])
        // The credential's numbers open Records with its requests made ready in the period (REQ-34-17).
        ->assertSee(route('records', ['scope' => 'ready', 'from' => '2026-10-01', 'to' => '2026-10-31', 'type' => $this->org->tor->id]));

    // Only the Transcript's 8 days are over its 7-day standard.
    expect(substr_count($response->getContent(), 'data-over-standard'))->toBe(1);
});

test('REQ-07-11 requests within their period are not overdue; open past-due ones are in the delay analysis', function () {
    $toDate = PerformanceMetrics::for(new ReportFilter('2026-10-01', '2026-10-13'));   // C (due 14th) not yet judged

    expect($toDate)->eligible->toBe(2)->onTime->toBe(1)->overdue->toBe(1)
        ->and(collect(reportRows(new DelayReport))->pluck(0)->all())->toContain($this->c->reference_no)->not->toContain($this->d->reference_no)
        ->and(PerformanceMetrics::for(new ReportFilter('2026-11-01', '2026-11-30')))->eligible->toBe(0)
        ->and(PerformanceMetrics::for(new ReportFilter('2026-11-01', '2026-11-30'))->onTimeRate())->toBeNull();
});

test('REQ-34-11 the clock stops at ready for release: an unclaimed credential ready in time is on time', function () {
    $unclaimed = CredentialRequest::factory()->create([
        'user_id' => $this->owner->id, 'credential_type_id' => $this->org->tor->id, 'college_id' => $this->org->ccs->id,
        'status' => RequestStatus::ReadyForRelease, 'submitted_at' => '2026-10-05 08:00', 'accepted_at' => '2026-10-05 09:00',
        'due_at' => '2026-10-15 23:59:59', 'ready_at' => '2026-10-15 10:00',   // still not claimed on the 20th
    ]);

    expect(PerformanceMetrics::for($this->filter))->eligible->toBe(4)->onTime->toBe(2)->overdue->toBe(2)
        ->and(collect(reportRows(new DelayReport))->pluck(0)->all())->not->toContain($unclaimed->reference_no)
        ->and(collect(reportRows(new TransactionReport))->keyBy(0)[$unclaimed->reference_no][9])->toBe('Yes');
});

test('REQ-34-02 the dashboard\'s office table, longest first, with what each office holds now', function () {
    expect(DepartmentReport::offices($this->filter)->map(fn ($row) => [$row['name'], $row['days'], $row['done'], $row['pending'], $row['oldest']])->all())->toBe([
        ['College of Computer Studies', 3.0, 1, 0, null],
        ['Office of the VP for Finance', 2.0, 0, 0, null],
        ['Office of the Registrar', 1.0, 1, 0, null],
        ['Office of the Chancellor', null, 0, 1, 5],   // C since Wed Oct 14: Thu, Fri, Sat, Mon, Tue
    ]);

    $this->actingAs($this->admin)->get(route('admin.dashboard', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertSeeInOrder(['Processing Time by Office', 'College of Computer Studies', '3 working days', 'Office of the VP for Finance', '2 working days',
            'Office of the Registrar', 'Office of the Chancellor', 'Processing Time by Credential'])
        ->assertSeeInOrder(['Office', 'Avg per document', 'Done', 'Pending'])
        ->assertDontSee('Oldest pending')->assertDontSee('Pending now')
        ->assertDontSeeHtml('data-slowest')->assertDontSeeHtml('bg-amber')
        // No bar graphs (REQ-34-04).
        ->assertDontSeeHtml('data-bar-track')->assertDontSeeHtml('rounded-e bg-primary')
        // Pending opens Records for the office; the name and average its finished requests, Done its completed ones.
        ->assertSee(route('records', ['status' => 'processing', 'office' => $this->org->chancellor->id]))
        ->assertDontSee(route('records', ['status' => 'processing', 'office' => $this->org->ccs->id]))   // nothing pending: no link
        ->assertSee(route('records', ['scope' => 'finished', 'from' => '2026-10-01', 'to' => '2026-10-31', 'at' => $this->org->ccs->id]))
        ->assertSee(route('records', ['scope' => 'done', 'from' => '2026-10-01', 'to' => '2026-10-31', 'at' => $this->org->ccs->id]))
        ->assertDontSee(route('records', ['scope' => 'done', 'from' => '2026-10-01', 'to' => '2026-10-31', 'at' => $this->org->vpf->id]))   // nothing done
        ->assertDontSee('office-documents')
        // The office and credential names are plain text.
        ->assertSeeHtml('<th scope="row" class="p-3 text-start font-medium text-ink">College of Computer Studies</th>')
        ->assertSeeHtml('<th scope="row" class="p-3 text-start font-medium text-ink">Transcript of Records</th>');
});

test('REQ-34-10 a delay has one of four causes, from the office holding the document', function () {
    $cause = function (?Department $office, VisitPurpose $purpose = VisitPurpose::Route): string {
        $visit = null;
        if ($office !== null) {
            $visit = (new OfficeVisit)->forceFill(['purpose' => $purpose, 'working_days' => 1]);
            $visit->setRelation('department', $office);
        }
        $request = (new CredentialRequest)->setRelation('openVisit', $visit)
            ->setRelation('visits', new Collection($visit === null ? [] : [$visit]));

        return DelayReport::causeOf($request)->label();
    };

    expect(collect(DelayCategory::inferred())->map->label()->all())
        ->toBe(['Registrar / evaluation', 'Pending signature', 'VP Finance documentary stamp', 'Document correction'])
        ->and($cause($this->org->ccs))->toBe('Pending signature')            // the Dean signs too
        ->and($cause($this->org->chancellor))->toBe('Pending signature')
        ->and($cause($this->org->vpf))->toBe('VP Finance documentary stamp')
        ->and($cause($this->org->registrarOffice))->toBe('Registrar / evaluation')
        ->and($cause($this->org->ccs, VisitPurpose::Correction))->toBe('Document correction')
        ->and($cause(null))->toBe('Registrar / evaluation');
});

test('REQ-34-06 the office performance report, longest first', function () {
    expect(reportRows(new DepartmentReport))->toBe([
        // No office deadlines in these visits, so no On time % (REQ-30-09).
        ['College of Computer Studies', 3.0, 1, 0, 0, null, null],
        ['Office of the VP for Finance', 2.0, 0, 1, 0, null, null],
        ['Office of the Registrar', 1.0, 1, 0, 0, null, null],
        ['Office of the Chancellor', null, 0, 0, 1, 5, null],
    ]);
});

test('REQ-34-07 the transaction report lists each request of the period, on time or not and why', function () {
    $report = new TransactionReport;
    $rows = collect(reportRows($report))->keyBy(0);

    expect($report->title())->toBe('Transaction Report')
        ->and($report->columns())->not->toContain('Program')
        ->and($report->filters())->toBe(['evaluator'])
        ->and($rows->keys()->all())->toBe(collect([$this->a, $this->b, $this->c])->pluck('reference_no')
        ->merge(CredentialRequest::whereIn('purpose', ['F', 'D', 'G', 'H'])->orderBy('submitted_at')->pluck('reference_no'))->all())
        ->and($rows[$this->a->reference_no])->toBe([
            $this->a->reference_no, '2026-10-02', '2026-10-10', $this->a->requester_name,
            'Transcript of Records', '2026-10-08', '2026-10-09', 6, 'Eva Luator', 'Yes', null,
        ])
        ->and(array_slice($rows[$this->b->reference_no], 9))->toBe(['Late', 'VP Finance documentary stamp'])
        ->and(array_slice($rows[$this->c->reference_no], 9))->toBe(['Overdue', 'Pending signature'])
        ->and(array_slice($rows[$this->d->reference_no], 9))->toBe(['Pending', null])
        ->and($rows[$this->g->reference_no][9])->toBe('Pending')
        ->and($rows->firstWhere(9, 'Cancelled'))->not->toBeNull();

    $byEvaluator = new ReportFilter('2026-10-01', '2026-10-31', evaluator: (string) $this->registrar->id);
    expect(collect(reportRows(new TransactionReport, $byEvaluator))->pluck(0)->all())
        ->toBe([$this->a->reference_no, $this->b->reference_no, $this->h->reference_no]);

    // Only the period and the evaluator: no credential, office or requests filter.
    $this->actingAs($this->admin)->get(route('admin.reports', ['report' => 'transactions', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertSee('All evaluators')->assertDontSee('All credentials')->assertDontSee('All offices')
        ->assertDontSee('Requested in the period');
});

test('REQ-34-08 the monthly summary shows January to December of the chosen year, and its totals tie', function () {
    expect(reportRows(new MonthlySummaryReport))->toBe([['October 2026', 7, 3, 1, 1, 1, 33.3, 66.7, 8.0]]);

    $year = reportRows(new MonthlySummaryReport, new ReportFilter('2026-01-01', '2026-12-31'));
    expect(collect($year)->pluck(0)->first())->toBe('January 2026')
        ->and(collect($year)->pluck(0)->last())->toBe('December 2026')
        ->and($year)->toHaveCount(12)
        ->and($year[8])->toBe(['September 2026', 2, 2, 1, 1, 0, 50.0, 50.0, 7.0])   // E ready on time; R ready Oct 1, after its due date
        ->and($year[9])->toBe(['October 2026', 7, 3, 1, 1, 1, 33.3, 66.7, 8.0])
        ->and($year[11])->toBe(['December 2026', 0, 0, 0, 0, 0, null, null, null])   // after today: nothing due yet
        ->and(collect($year)->every(fn (array $row) => $row[2] === $row[3] + $row[4] + $row[5]))->toBeTrue();

    // The page picks a year in place of From and To.
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('report', 'monthly')->assertSet('year', '2026')
        ->assertSee('Year')->assertDontSee('From')
        ->assertViewHas('rows', fn ($rows) => $rows->total() === 12);
});

test('REQ-07-18 the delay analysis separates the holding office from the cause', function () {
    $rows = collect(reportRows(new DelayReport))->keyBy(0);

    expect($rows)->toHaveCount(2)
        ->and($rows[$this->b->reference_no])->toBe([
            $this->b->reference_no, 'Transcript of Records', '2026-10-03', '2026-10-12', '2026-10-14', '—', 'Released',
            2, 0, 'VP Finance documentary stamp', null, null,   // ready 2 days late (Tue, Wed); inferred from the slowest office
        ])
        ->and($rows[$this->c->reference_no])->toBe([
            $this->c->reference_no, 'Transcript of Records', '2026-10-05', '2026-10-14', null, 'Office of the Chancellor', 'In progress',
            5, 0, 'Pending signature', 'The Chancellor is abroad', 'Sign on return',
        ]);
});

test('REQ-34-15 the evaluator performance report', function () {
    // Taken up, complete, returned for clarification, sent for compliance, declined; A 2 h, B 4 h, H 6 h; H still open.
    expect(reportRows(new EvaluatorReport))->toBe([['Eva Luator', 3, 2, 0, 1, 0, 4.0, 1]])
        ->and((new EvaluatorReport)->title())->toBe('Evaluator Performance Report');
});

test('REQ-34-16 Export CSV downloads every row of the current report with its filters and sort', function () {
    $this->actingAs($this->admin)->get(route('admin.reports'))->assertSee('Export CSV');

    $component = Livewire::actingAs($this->admin)->test(Index::class)
        ->set('report', 'transactions')->set('from', '2026-10-01')->set('to', '2026-10-31')
        ->call('sortBy', 1)->call('sortBy', 1);   // newest first

    $response = $component->instance()->export();
    ob_start();
    $response->sendContent();
    $lines = array_values(array_filter(explode("\n", (string) ob_get_clean())));

    expect($response->headers->get('content-disposition'))->toContain('transactions-2026-10-01-2026-10-31.csv')
        ->and(str_getcsv($lines[0], escape: ''))->toBe((new TransactionReport)->columns())
        ->and($lines)->toHaveCount(8)   // the header and the 7 October requests
        ->and(str_getcsv($lines[1], escape: '')[0])->toBe($this->h->reference_no);

    $component->set('report', 'monthly');
    expect($component->instance()->export()->headers->get('content-disposition'))->toContain('monthly-2026.csv');
});

test('REQ-07-20 reports are for the admin only', function () {
    $this->actingAs($this->admin)->get(route('admin.reports', ['report' => 'transactions', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()->assertSee($this->h->reference_no);
    $this->actingAs($this->owner)->get(route('admin.reports'))->assertForbidden();
    $this->actingAs(User::factory()->departmentStaff($this->org->vpf)->create())->get(route('admin.reports'))->assertForbidden();
});
