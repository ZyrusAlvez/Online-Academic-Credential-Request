<?php

namespace App\Reports;

use App\Models\CredentialRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The monthly summary (REQ-34-08): per month, the requests received and, of those due, how many
 * were released on time, released late or are still pending. The three always add up to Due
 * (pilot consultation §10).
 */
final class MonthlySummaryReport implements Report
{
    public function title(): string
    {
        return 'Monthly Summary';
    }

    public function description(): string
    {
        return 'Requests due each month: released on time, released late and still pending.';
    }

    public function columns(): array
    {
        return ['Month', 'Received', 'Due', 'On time', 'Late', 'Still pending', 'On time %', 'Overdue %', 'Avg processing (working days)'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $start = $filter->start;
        $end = $filter->judgedUntil();

        if ($start->greaterThan($end)) {
            return [];
        }

        $received = $this->byMonth($filter->requests()->whereBetween('submitted_at', [$start, $end]), 'submitted_at')
            ->get()
            ->mapWithKeys(fn (CredentialRequest $row): array => [(string) $row->getAttribute('month') => (int) $row->getAttribute('n')]);

        $due = $this->byMonth(PerformanceMetrics::eligible($filter), 'effective_due_at')
            ->selectRaw('SUM(released_at IS NOT NULL AND released_at <= effective_due_at) as on_time')
            ->selectRaw('SUM(released_at > effective_due_at) as late')
            ->get()
            ->keyBy('month');

        $processing = $this->byMonth($filter->requests()->whereBetween('released_at', [$start, $end])->whereNotNull('processing_working_days'), 'released_at')
            ->selectRaw('AVG(processing_working_days) as days')
            ->get()
            ->keyBy('month');

        $rows = [];

        for ($month = $start->startOfMonth(); $month->lessThanOrEqualTo($end); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $row = $due->get($key);
            $total = (int) ($row?->getAttribute('n') ?? 0);
            $onTime = (int) ($row?->getAttribute('on_time') ?? 0);
            $late = (int) ($row?->getAttribute('late') ?? 0);
            $days = $processing->get($key)?->getAttribute('days');

            $rows[] = [
                $month->format('F Y'),
                (int) ($received[$key] ?? 0),
                $total,
                $onTime,
                $late,
                $total - $onTime - $late,
                $total === 0 ? null : round($onTime / $total * 100, 1),
                $total === 0 ? null : round(($total - $onTime) / $total * 100, 1),
                $days === null ? null : round((float) $days, 1),
            ];
        }

        return $rows;
    }

    /**
     * The query grouped by the month of a timestamp column, with its count as `n`.
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    private function byMonth(Builder $query, string $column): Builder
    {
        return $query
            ->select(DB::raw("DATE_FORMAT({$column}, '%Y-%m') as month"))
            ->selectRaw('COUNT(*) as n')
            ->groupBy('month');
    }
}
