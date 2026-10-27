<?php

namespace App\Reports;

use App\Enums\VisitPurpose;
use App\Models\OfficeVisit;

/**
 * Credential tracking (REQ-07-17): the complete routing history of requests accepted in the
 * period, one row per office visit.
 */
final class TrackingReport implements Report
{
    public function title(): string
    {
        return 'Credential tracking report';
    }

    public function description(): string
    {
        return 'Complete routing history, timestamps and return records of requests accepted in the period.';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Credential', 'Office', 'Visit', 'Forwarded', 'Received', 'Released', 'Working days', 'Status', 'Return reason'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $requests = $filter->requests()->whereBetween('accepted_at', [$filter->start, $filter->end])->select('credential_requests.id');

        return OfficeVisit::query()
            ->whereIn('credential_request_id', $requests)
            ->with(['department', 'step', 'returnedTo', 'credentialRequest.credentialType'])
            ->orderBy('credential_request_id')
            ->orderBy('id')
            ->lazy(200)
            ->map(fn (OfficeVisit $visit): array => [
                $visit->credentialRequest->reference_no,
                $visit->credentialRequest->credentialType->name,
                $visit->department->name,
                ($visit->purpose === VisitPurpose::Route ? $visit->step->task->label() : $visit->purpose->label()).($visit->visit_no > 1 ? " (visit {$visit->visit_no})" : ''),
                $visit->forwarded_at->format('Y-m-d H:i'),
                $visit->received_at?->format('Y-m-d H:i'),
                $visit->completed_at?->format('Y-m-d H:i'),
                $visit->working_days,
                $visit->status->label(),
                $visit->return_reason !== null ? "To {$visit->returnedTo?->name}: {$visit->return_reason}" : null,
            ]);
    }
}
