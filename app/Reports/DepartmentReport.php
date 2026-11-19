<?php

namespace App\Reports;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Office performance, the bottleneck report (REQ-34-06): per office, the average working days per
 * document finished in the period, done, returned, pending now, the longest pending and the share
 * done by the office's own deadline (REQ-30-09). Longest average first.
 */
final class DepartmentReport implements Report
{
    public function title(): string
    {
        return 'Office Performance Report';
    }

    public function description(): string
    {
        return 'Time each office takes per document, longest first, and what it holds now.';
    }

    public function columns(): array
    {
        return ['Office', 'Avg working days per document', 'Done', 'Returned', 'Pending now', 'Longest pending (working days)', 'On time %'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        return self::offices($filter)
            ->map(fn (array $office): array => [
                $office['name'], $office['days'], $office['done'], $office['returned'],
                $office['pending'], $office['oldest'], $office['onTime'],
            ])
            ->all();
    }

    /**
     * @return Collection<int, array{id: int<0, max>, name: string, days: ?float, done: int, returned: int, pending: int, oldest: ?int, onTime: ?float}>
     */
    public static function offices(ReportFilter $filter): Collection
    {
        $closed = $filter->visits()->whereBetween('office_visits.completed_at', [$filter->start, $filter->end])
            ->whereIn('office_visits.status', [VisitStatus::Completed, VisitStatus::Returned])
            ->groupBy('office_visits.department_id')
            ->get([
                'office_visits.department_id',
                DB::raw("SUM(office_visits.status = '".VisitStatus::Completed->value."') as done"),
                DB::raw("SUM(office_visits.status = '".VisitStatus::Returned->value."') as returned"),
                DB::raw('AVG(office_visits.working_days) as days'),
                // Task done by the office's own deadline, of the visits that had one (REQ-30-09).
                DB::raw('SUM(office_visits.due_at IS NOT NULL) as with_deadline'),
                DB::raw('SUM(office_visits.due_at IS NOT NULL AND COALESCE(office_visits.done_at, office_visits.completed_at) <= office_visits.due_at) as on_time'),
            ])
            ->keyBy('department_id');

        // Pending at the office holding the paper: its sender until it is received (REQ-17-18).
        [$holder, $bindings] = OfficeVisit::holderSql();
        $held = $filter->heldVisits()
            ->selectRaw("{$holder} as holder_id", $bindings)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MIN(COALESCE(office_visits.received_at, office_visits.forwarded_at)) as since')
            ->groupBy('holder_id')
            ->get()
            ->keyBy(fn (OfficeVisit $row): int => (int) $row->getAttribute('holder_id'));

        $calendar = app(WorkingCalendar::class);
        $ids = $closed->keys()->map(fn ($id): int => (int) $id)->merge($held->keys())->unique();

        return Department::whereIn('id', $ids)->get()
            ->map(function (Department $office) use ($closed, $held, $calendar): array {
                $row = $closed->get($office->id);
                $pending = $held->get($office->id);
                $withDeadline = (int) ($row?->getAttribute('with_deadline') ?? 0);
                $since = $pending?->getAttribute('since');

                return [
                    'id' => $office->id,
                    'name' => $office->name,
                    'days' => $row?->getAttribute('days') === null ? null : round((float) $row->getAttribute('days'), 1),
                    'done' => (int) ($row?->getAttribute('done') ?? 0),
                    'returned' => (int) ($row?->getAttribute('returned') ?? 0),
                    'pending' => (int) ($pending?->getAttribute('total') ?? 0),
                    'oldest' => $since === null ? null : $calendar->workingDaysBetween(CarbonImmutable::parse((string) $since), now()),
                    'onTime' => $withDeadline === 0 ? null : round((int) $row->getAttribute('on_time') * 100 / $withDeadline, 1),
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($b['days'] ?? -1) <=> ($a['days'] ?? -1),
                fn (array $a, array $b): int => ($b['oldest'] ?? -1) <=> ($a['oldest'] ?? -1),
                fn (array $a, array $b): int => $a['name'] <=> $b['name'],
            ])
            ->values();
    }
}
