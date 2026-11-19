<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The requests behind a dashboard number, for Records (REQ-34-17). Each selection uses the same
 * conditions as the number it comes from.
 */
final class DashboardScope
{
    /** Total requests: submitted in the period. */
    public const SUBMITTED = 'submitted';

    /** Ready on time: due in the period and ready for release by the due date (REQ-34-11). */
    public const ON_TIME = 'on_time';

    /** Processing Time by Credential: made ready in the period (REQ-34-09). */
    public const READY = 'ready';

    /** An office's name and average: finished there in the period, done or returned (REQ-34-02). */
    public const FINISHED = 'finished';

    /** An office's Done: completed there in the period. */
    public const DONE = 'done';

    public function __construct(
        public readonly string $scope,
        public readonly string $from,
        public readonly string $to,
        public readonly string $at = '',
    ) {}

    public function active(): bool
    {
        return in_array($this->scope, [self::SUBMITTED, self::ON_TIME, self::READY, self::FINISHED, self::DONE], true);
    }

    /**
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public function apply(Builder $query): Builder
    {
        $period = new ReportFilter($this->from, $this->to);
        $between = [$period->start, $period->end];

        return match ($this->scope) {
            self::SUBMITTED => $query->whereBetween('submitted_at', $between),
            // PerformanceMetrics::eligible() and its on-time count (REQ-07-11).
            self::ON_TIME => $query->whereNotNull('accepted_at')
                ->whereNotIn('status', [RequestStatus::Cancelled, RequestStatus::Declined])
                ->whereBetween('effective_due_at', [$period->start, $period->judgedUntil()])
                ->whereNotNull('ready_at')
                ->whereColumn('ready_at', '<=', 'effective_due_at'),
            self::READY => $query->whereBetween('ready_at', $between)->whereNotNull('processing_working_days'),
            self::FINISHED, self::DONE => $query->whereHas('visits', fn (Builder $visits) => $visits
                ->where('office_visits.department_id', (int) $this->at)
                ->whereBetween('office_visits.completed_at', $between)
                ->whereIn('office_visits.status', $this->scope === self::DONE
                    ? [VisitStatus::Completed]
                    : [VisitStatus::Completed, VisitStatus::Returned])),
            default => $query,
        };
    }

    /** The chip above the list, e.g. "Ready on time · due Oct 1 – Oct 31, 2026". */
    public function label(): string
    {
        $period = new ReportFilter($this->from, $this->to);
        $dates = $period->start->format('M j').' – '.$period->end->format('M j, Y');
        $office = Department::find((int) $this->at)?->name ?? __('the office');

        return match ($this->scope) {
            self::SUBMITTED => __('Submitted :dates', ['dates' => $dates]),
            self::ON_TIME => __('Ready on time · due :dates', ['dates' => $dates]),
            self::READY => __('Ready for release :dates', ['dates' => $dates]),
            self::FINISHED => __('Finished at :office · :dates', ['office' => $office, 'dates' => $dates]),
            self::DONE => __('Done at :office · :dates', ['office' => $office, 'dates' => $dates]),
            default => '',
        };
    }
}
