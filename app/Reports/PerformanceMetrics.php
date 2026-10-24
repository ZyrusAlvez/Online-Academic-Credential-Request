<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The client's performance indicators (Proposed Workflow IX-C; REQ-07-04, 11, 12), computed in
 * grouped queries over stored values.
 */
final class PerformanceMetrics
{
    /**
     * @param  Collection<int, array{id: int, name: string, visits: int, days: float}>  $byOffice
     */
    public function __construct(
        public readonly int $received,
        public readonly int $eligible,
        public readonly int $onTime,
        public readonly int $overdue,
        public readonly ?float $avgProcessingDays,
        public readonly ?float $avgOfficeDays,
        public readonly int $overdueWorkload,
        public readonly int $returns,
        public readonly int $closedVisits,
        public readonly ?float $avgInitialEvaluationHours,
        public readonly Collection $byOffice,
    ) {}

    public static function for(ReportFilter $filter): self
    {
        $eligible = self::eligible($filter);
        $onTime = (clone $eligible)->whereNotNull('released_at')->whereColumn('released_at', '<=', 'effective_due_at')->count();
        $eligibleCount = $eligible->count();

        $closedVisits = $filter->visits()
            ->whereIn('office_visits.status', [VisitStatus::Completed, VisitStatus::Returned])
            ->whereBetween('office_visits.completed_at', [$filter->start, $filter->end]);

        $initial = $filter->requests()
            ->whereBetween('evaluation_started_at', [$filter->start, $filter->end])
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, submitted_at, evaluation_started_at)'));

        $avgProcessing = $filter->requests()
            ->whereBetween('released_at', [$filter->start, $filter->end])
            ->whereNotNull('processing_working_days')
            ->avg('processing_working_days');

        $avgOffice = (clone $closedVisits)->whereNotNull('office_visits.working_days')->avg('office_visits.working_days');

        return new self(
            received: $filter->requests()->whereBetween('submitted_at', [$filter->start, $filter->end])->count(),
            eligible: $eligibleCount,
            onTime: $onTime,
            overdue: $eligibleCount - $onTime,
            avgProcessingDays: $avgProcessing === null ? null : round((float) $avgProcessing, 1),
            avgOfficeDays: $avgOffice === null ? null : round((float) $avgOffice, 1),
            overdueWorkload: $filter->requests()->overdue()->count(),
            returns: (clone $closedVisits)->where('office_visits.status', VisitStatus::Returned)->count(),
            closedVisits: (clone $closedVisits)->count(),
            avgInitialEvaluationHours: $initial === null ? null : round((float) $initial / 60, 1),
            byOffice: self::byOffice($closedVisits),
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

    public function returnRate(): ?float
    {
        return $this->closedVisits === 0 ? null : round($this->returns / $this->closedVisits * 100, 1);
    }

    /**
     * Average working days per office, longest first (bottleneck analysis).
     *
     * @param  Builder<OfficeVisit>  $closedVisits
     * @return Collection<int, array{id: int, name: string, visits: int, days: float}>
     */
    private static function byOffice(Builder $closedVisits): Collection
    {
        return (clone $closedVisits)
            ->join('departments', 'departments.id', '=', 'office_visits.department_id')
            ->whereNotNull('office_visits.working_days')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc(DB::raw('avg(office_visits.working_days)'))
            ->orderBy('departments.name')
            ->get(['departments.id', 'departments.name', DB::raw('count(*) as visits'), DB::raw('avg(office_visits.working_days) as days')])
            ->map(fn ($row) => [
                'id' => (int) $row->getAttribute('id'),
                'name' => (string) $row->getAttribute('name'),
                'visits' => (int) $row->getAttribute('visits'),
                'days' => round((float) $row->getAttribute('days'), 1),
            ]);
    }
}
