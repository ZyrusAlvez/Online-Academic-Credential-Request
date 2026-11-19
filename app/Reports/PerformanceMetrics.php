<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's indicators (REQ-34-01, REQ-34-09), with the client's formulas (Proposed Workflow
 * IX-C; REQ-07-11).
 */
final class PerformanceMetrics
{
    public function __construct(
        public readonly int $received,
        public readonly int $eligible,
        public readonly int $onTime,
        public readonly int $overdue,
    ) {}

    public static function for(ReportFilter $filter): self
    {
        $eligible = self::eligible($filter);
        // On time = ready for release by the due date (REQ-34-11).
        $onTime = (clone $eligible)->whereNotNull('ready_at')->whereColumn('ready_at', '<=', 'effective_due_at')->count();
        $eligibleCount = $eligible->count();

        return new self(
            received: $filter->requests()->whereBetween('submitted_at', [$filter->start, $filter->end])->count(),
            eligible: $eligibleCount,
            onTime: $onTime,
            overdue: $eligibleCount - $onTime,
        );
    }

    /**
     * Average processing time per credential (REQ-34-09; Proposed Workflow XIII.2): working days
     * from acceptance to ready for release of the requests made ready in the period (REQ-34-11),
     * longest first.
     *
     * @return Collection<int, array{name: string, ready: int, days: float, standard: int}>
     */
    public static function byCredential(ReportFilter $filter): Collection
    {
        $rows = $filter->requests()
            ->whereBetween('credential_requests.ready_at', [$filter->start, $filter->end])
            ->whereNotNull('credential_requests.processing_working_days')
            ->groupBy('credential_requests.credential_type_id')
            ->get([
                'credential_requests.credential_type_id',
                DB::raw('COUNT(*) as ready'),
                DB::raw('AVG(credential_requests.processing_working_days) as days'),
            ])
            ->keyBy('credential_type_id');

        return CredentialType::whereIn('id', $rows->keys())->get()
            ->map(function (CredentialType $type) use ($rows): array {
                $row = $rows->get($type->id);

                return [
                    'name' => $type->name,
                    'ready' => (int) $row?->getAttribute('ready'),
                    'days' => round((float) $row?->getAttribute('days'), 1),
                    'standard' => $type->processing_days,
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => $b['days'] <=> $a['days'],
                fn (array $a, array $b): int => $a['name'] <=> $b['name'],
            ])
            ->values();
    }

    /**
     * Accepted requests (not cancelled or declined) due in the period and not after now: the
     * denominator of the on-time and overdue rates.
     *
     * @return Builder<CredentialRequest>
     */
    public static function eligible(ReportFilter $filter): Builder
    {
        return $filter->requests()
            ->whereNotNull('accepted_at')
            ->whereNotIn('status', [RequestStatus::Cancelled, RequestStatus::Declined])
            ->whereBetween('effective_due_at', [$filter->start, $filter->judgedUntil()]);
    }

    public function onTimeRate(): ?float
    {
        return $this->eligible === 0 ? null : round($this->onTime / $this->eligible * 100, 1);
    }

    public function overdueRate(): ?float
    {
        return $this->eligible === 0 ? null : round($this->overdue / $this->eligible * 100, 1);
    }
}
