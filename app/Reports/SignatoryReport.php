<?php

namespace App\Reports;

use App\Enums\VisitStatus;
use App\Models\OfficeVisit;
use App\Services\WorkingCalendar;

/**
 * Signatory monitoring (REQ-07-16): documents pending at each office now and for how long. The
 * date range does not apply: it is a snapshot.
 */
final class SignatoryReport implements Report
{
    public function title(): string
    {
        return 'Signatory monitoring report';
    }

    public function description(): string
    {
        return 'Documents pending at each office right now and how long they have been pending (the date range does not apply).';
    }

    public function columns(): array
    {
        return ['Office', 'Tracking no.', 'Credential', 'Task', 'Status', 'Since', 'Working days pending', 'Due', 'Overdue'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $calendar = app(WorkingCalendar::class);

        // Listed under the office holding the paper: its sender until it is received (REQ-17-18).
        return $filter->heldVisits()
            ->with(['department', 'fromDepartment', 'step', 'credentialRequest.credentialType'])
            ->get()
            ->sortBy(fn (OfficeVisit $visit) => [$visit->holder()->name, ($visit->received_at ?? $visit->forwarded_at)->timestamp])
            ->map(function (OfficeVisit $visit) use ($calendar): array {
                $since = $visit->received_at ?? $visit->forwarded_at;
                $request = $visit->credentialRequest;

                return [
                    $visit->holder()->name,
                    $request->reference_no,
                    $request->credentialType->name,
                    $visit->purpose->value === 'route' ? $visit->step->task->label() : $visit->purpose->label(),
                    $visit->status === VisitStatus::InTransit
                        ? 'Forwarded to '.$visit->department->name.', not yet received'
                        : $visit->status->label(),
                    $since->format('Y-m-d H:i'),
                    $calendar->workingDaysBetween($since, now()),
                    $request->effective_due_at?->format('Y-m-d'),
                    $request->isOverdue() ? 'Yes' : 'No',
                ];
            })
            ->values()
            ->all();
    }
}
