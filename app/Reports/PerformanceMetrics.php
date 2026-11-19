<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dashboard's four indicators (REQ-34-01), with the client's formulas (Proposed Workflow
 * IX-C; REQ-07-11).
 */
final class PerformanceMetrics
{
    public function __construct(
        public readonly int $received,
        public readonly int $eligible,
        public readonly int $onTime,
        public readonly int $overdue,
        public readonly ?float $avgProcessingDays,
    ) {}

    public static function for(ReportFilter $filter): self
    {
        $eligible = self::eligible($filter);
        $onTime = (clone $eligible)->whereNotNull('released_at')->whereColumn('released_at', '<=', 'effective_due_at')->count();
        $eligibleCount = $eligible->count();

        $avgProcessing = $filter->requests()
            ->whereBetween('released_at', [$filter->start, $filter->end])
            ->whereNotNull('processing_working_days')
            ->avg('processing_working_days');

        return new self(
            received: $filter->requests()->whereBetween('submitted_at', [$filter->start, $filter->end])->count(),
            eligible: $eligibleCount,
            onTime: $onTime,
            overdue: $eligibleCount - $onTime,
            avgProcessingDays: $avgProcessing === null ? null : round((float) $avgProcessing, 1),
        );
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
