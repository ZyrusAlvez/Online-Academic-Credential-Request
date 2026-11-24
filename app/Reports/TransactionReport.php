<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;

/**
 * The Transaction Report (REQ-34-07, pilot consultation §10): every request submitted in the
 * period, by evaluator, whether it was ready for release on time, and the cause when it was not. It
 * replaces the evaluators' logbook and monthly accomplishment report.
 */
final class TransactionReport implements Report
{
    public function title(): string
    {
        return 'Transaction Report';
    }

    public function description(): string
    {
        return 'Every request submitted in the period, with its expected and actual release.';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Requested', 'Expected release', 'Requester', 'Credential', 'Ready for release', 'Released', 'Processing (working days)', 'Evaluator', 'On time', 'Cause of delay'];
    }

    public function filters(): array
    {
        return ['evaluator'];
    }

    public function shows(): array
    {
        return [];
    }

    public function showLabel(): string
    {
        return '';
    }

    public function rows(ReportFilter $filter): iterable
    {
        return $filter->requests()
            ->whereBetween('submitted_at', [$filter->start, $filter->end])
            ->orderBy('submitted_at')
            ->with(['credentialType', 'evaluator', 'openVisit.department', 'visits.department', 'delayNotes'])
            ->orderBy('id')
            ->lazy()
            ->map(function (CredentialRequest $request): array {
                $timeliness = self::timeliness($request);

                return [
                    $request->reference_no,
                    $request->submitted_at?->format('Y-m-d'),
                    $request->effective_due_at?->format('Y-m-d'),
                    $request->requester_name,
                    $request->credentialType->name,
                    $request->ready_at?->format('Y-m-d'),
                    $request->released_at?->format('Y-m-d'),
                    $request->processing_working_days,
                    $request->evaluator?->name,
                    $timeliness,
                    in_array($timeliness, ['Late', 'Overdue'], true) ? DelayReport::causeOf($request)->label() : null,
                ];
            });
    }

    /** Judged when the credential was ready for release (REQ-34-11). */
    private static function timeliness(CredentialRequest $request): string
    {
        $due = $request->effective_due_at;

        return match (true) {
            $request->ready_at !== null => $due === null || $request->ready_at->lessThanOrEqualTo($due) ? 'Yes' : 'Late',
            in_array($request->status, [RequestStatus::Declined, RequestStatus::Cancelled], true) => $request->status->label(),
            $due !== null && $due->isPast() => 'Overdue',
            default => 'Pending',
        };
    }
}
