<?php

namespace App\Reports;

use App\Enums\VisitStatus;
use App\Models\OfficeVisit;
use App\Services\WorkingCalendar;

/**
 * Office documents (REQ-34-12): the documents behind an office's numbers on the dashboard, one row
 * per office visit. The finished rows are the ones Office Performance counts (REQ-34-06); the
 * pending rows are the papers the office holds now, in transit at the sender (REQ-17-18).
 */
final class OfficeDocumentsReport implements Report
{
    public const FINISHED = 'finished';

    public const DONE = 'done';

    public const RETURNED = 'returned';

    public const PENDING = 'pending';

    public function title(): string
    {
        return 'Office Documents';
    }

    public function description(): string
    {
        return 'Each document an office finished in the period or holds now, with its working days.';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Credential', 'Office', 'Received', 'Finished', 'Working days', 'Result'];
    }

    public function filters(): array
    {
        return ['type', 'office'];
    }

    public function shows(): array
    {
        return [
            self::FINISHED => 'Finished (done or returned)',
            self::DONE => 'Done',
            self::RETURNED => 'Returned',
            self::PENDING => 'Pending',
        ];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $rows = [];

        if ($filter->show !== self::PENDING) {
            $statuses = match ($filter->show) {
                self::DONE => [VisitStatus::Completed],
                self::RETURNED => [VisitStatus::Returned],
                default => [VisitStatus::Completed, VisitStatus::Returned],
            };

            $finished = $filter->visits()
                ->whereBetween('office_visits.completed_at', [$filter->start, $filter->end])
                ->whereIn('office_visits.status', $statuses)
                ->with(['credentialRequest.credentialType', 'department'])
                ->orderBy('office_visits.completed_at')
                ->orderBy('office_visits.id')
                ->get();

            foreach ($finished as $visit) {
                $rows[] = [
                    $visit->credentialRequest->reference_no,
                    $visit->credentialRequest->credentialType->name,
                    $visit->department->name,
                    $visit->received_at?->format('Y-m-d'),
                    $visit->completed_at?->format('Y-m-d'),
                    $visit->working_days,
                    $visit->status === VisitStatus::Returned ? 'Returned' : 'Done',
                ];
            }
        }

        if (in_array($filter->show, ['', self::PENDING], true)) {
            $calendar = app(WorkingCalendar::class);

            $held = $filter->heldVisits()
                ->with(['credentialRequest.credentialType', 'department', 'fromDepartment'])
                ->get()
                ->sortBy(fn (OfficeVisit $visit) => $visit->received_at ?? $visit->forwarded_at);

            foreach ($held as $visit) {
                $rows[] = [
                    $visit->credentialRequest->reference_no,
                    $visit->credentialRequest->credentialType->name,
                    $visit->holder()->name,
                    $visit->received_at?->format('Y-m-d'),
                    null,
                    $calendar->workingDaysBetween($visit->received_at ?? $visit->forwarded_at, now()),
                    'Pending',
                ];
            }
        }

        return $rows;
    }
}
