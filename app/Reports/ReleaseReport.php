<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Services\WorkingCalendar;
use Illuminate\Database\Eloquent\Builder;

/**
 * Release report (REQ-07-19): credentials ready for release now, released in the period and to
 * whom, and unclaimed ones (ready for more than 10 working days).
 */
final class ReleaseReport implements Report
{
    public const UNCLAIMED_AFTER = 10;

    public function title(): string
    {
        return 'Release report';
    }

    public function description(): string
    {
        return 'Credentials ready for release, released in the period (and to whom), and unclaimed after 10 working days.';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Requester', 'Credential', 'Release status', 'Ready on', 'Released on', 'Received by', 'Working days ready'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $calendar = app(WorkingCalendar::class);

        return $filter->requests()
            ->where(fn (Builder $q) => $q
                ->where('status', RequestStatus::ReadyForRelease)
                ->orWhere(fn (Builder $q) => $q->where('status', RequestStatus::Released)->whereBetween('released_at', [$filter->start, $filter->end])))
            ->with(['student', 'credentialType', 'release'])
            ->orderBy('ready_at')
            ->get()
            ->map(function (CredentialRequest $request) use ($calendar): array {
                $waited = $request->ready_at === null ? null : $calendar->workingDaysBetween($request->ready_at, $request->released_at ?? now());
                $status = match (true) {
                    $request->status === RequestStatus::Released => 'Released',
                    $waited !== null && $waited > self::UNCLAIMED_AFTER => 'Unclaimed',
                    default => 'Ready for release',
                };

                return [
                    $request->reference_no,
                    $request->student->name,
                    $request->credentialType->name,
                    $status,
                    $request->ready_at?->format('Y-m-d'),
                    $request->released_at?->format('Y-m-d'),
                    $request->release !== null ? "{$request->release->recipient_name} ({$request->release->recipient_type->label()})" : null,
                    $waited,
                ];
            })
            ->all();
    }
}
