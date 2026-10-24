<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

/**
 * Department performance (REQ-07-16): per office, documents received and released in the period,
 * returns, documents pending now and the average working days per completed visit.
 */
final class DepartmentReport implements Report
{
    public function title(): string
    {
        return 'Department performance report';
    }

    public function description(): string
    {
        return 'Documents received and released, average processing time, returns and pending items, per office.';
    }

    public function columns(): array
    {
        return ['Office', 'Received', 'Released', 'Returned', 'Pending now', 'Avg working days per visit'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $range = [$filter->start, $filter->end];

        $received = $filter->visits()->whereBetween('office_visits.received_at', $range)
            ->groupBy('office_visits.department_id')->pluck(DB::raw('COUNT(*)'), 'office_visits.department_id');

        $closed = $filter->visits()->whereBetween('office_visits.completed_at', $range)
            ->whereIn('office_visits.status', [VisitStatus::Completed, VisitStatus::Returned])
            ->groupBy('office_visits.department_id')
            ->get([
                'office_visits.department_id',
                DB::raw("SUM(office_visits.status = '".VisitStatus::Completed->value."') as released"),
                DB::raw("SUM(office_visits.status = '".VisitStatus::Returned->value."') as returned"),
                DB::raw('AVG(office_visits.working_days) as days'),
            ])
            ->keyBy('department_id');

        $pending = $filter->visits()
            ->whereIn('office_visits.status', [VisitStatus::InTransit, VisitStatus::Received])
            ->whereHas('credentialRequest', fn ($q) => $q->whereIn('status', RequestStatus::processing()))
            ->groupBy('office_visits.department_id')->pluck(DB::raw('COUNT(*)'), 'office_visits.department_id');

        $ids = $received->keys()->merge($closed->keys())->merge($pending->keys())->unique();

        return Department::whereIn('id', $ids)->get()
            ->map(function (Department $office) use ($received, $closed, $pending): array {
                $row = $closed->get($office->id);

                return [
                    $office->name,
                    (int) ($received[$office->id] ?? 0),
                    (int) ($row?->getAttribute('released') ?? 0),
                    (int) ($row?->getAttribute('returned') ?? 0),
                    (int) ($pending[$office->id] ?? 0),
                    $row?->getAttribute('days') === null ? null : round((float) $row->getAttribute('days'), 1),
                ];
            })
            ->sortByDesc(fn (array $row) => $row[5] ?? -1)
            ->values()
            ->all();
    }
}
