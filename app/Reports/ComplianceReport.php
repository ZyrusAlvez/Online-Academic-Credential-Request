<?php

namespace App\Reports;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Services\WorkingCalendar;

/**
 * Customer compliance (REQ-07-19): requests waiting for the requester's requirements,
 * clarification or payment, now. The date range does not apply: it is a snapshot.
 */
final class ComplianceReport implements Report
{
    private const WAITING = [
        RequestStatus::PendingCompliance,
        RequestStatus::ReturnedForCustomerAction,
        RequestStatus::AwaitingPayment,
        RequestStatus::PaymentVerification,
    ];

    public function title(): string
    {
        return 'Customer compliance report';
    }

    public function description(): string
    {
        return 'Requests waiting for missing requirements, clarification or payment right now (the date range does not apply).';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Requester', 'Contact', 'Credential', 'Status', 'Waiting for', 'Since', 'Working days waiting'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $calendar = app(WorkingCalendar::class);

        return $filter->requests()
            ->whereIn('status', self::WAITING)
            ->with(['student', 'credentialType', 'deficiencies', 'events'])
            ->orderBy('id')
            ->get()
            ->map(function (CredentialRequest $request) use ($calendar): array {
                $since = $request->events->last(fn ($event) => $event->to_status === $request->status)->created_at ?? $request->updated_at;

                return [
                    $request->reference_no,
                    $request->student->name,
                    $request->student->contact_number,
                    $request->credentialType->name,
                    $request->status->label(),
                    $this->waitingFor($request),
                    $since?->format('Y-m-d'),
                    $since === null ? null : $calendar->workingDaysBetween($since, now()),
                ];
            })
            ->all();
    }

    private function waitingFor(CredentialRequest $request): string
    {
        return match ($request->status) {
            RequestStatus::PendingCompliance => 'Missing: '.$request->deficiencies->whereNull('resolved_at')->pluck('description')->implode('; '),
            RequestStatus::ReturnedForCustomerAction => (string) $request->events->last(fn ($event) => $event->type === RequestEventType::CustomerActionRequested)?->remarks,
            RequestStatus::AwaitingPayment => 'Payment of ₱'.number_format((float) $request->amount_due, 2),
            default => 'Verification of the proof of payment',
        };
    }
}
