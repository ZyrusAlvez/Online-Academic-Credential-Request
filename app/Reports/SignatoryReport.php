<?php

namespace App\Reports;

use App\Enums\RequestStatus;
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

        return $filter->visits()
            ->whereIn('office_visits.status', [VisitStatus::InTransit, VisitStatus::Received])
            ->whereHas('credentialRequest', fn ($q) => $q->whereIn('status', RequestStatus::processing()))
            ->with(['department', 'step', 'credentialRequest.credentialType'])
            ->get()
            ->sortBy(fn (OfficeVisit $visit) => [$visit->department->name, ($visit->received_at ?? $visit->forwarded_at)->timestamp])
            ->map(function (OfficeVisit $visit) use ($calendar): array {
                $since = $visit->received_at ?? $visit->forwarded_at;
                $request = $visit->credentialRequest;

                return [
                    $visit->department->name,
                    $request->reference_no,
                    $request->credentialType->name,
                    $visit->purpose->value === 'route' ? $visit->step->task->label() : $visit->purpose->label(),
                    $visit->status->label(),
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
