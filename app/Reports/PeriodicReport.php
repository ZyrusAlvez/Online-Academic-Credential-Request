<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Daily monitoring, weekly and monthly performance reports (REQ-07-14): per day, week or month,
 * requests received, accepted, released, released on time, overdue and pending at the end.
 */
final class PeriodicReport implements Report
{
    /** The daily report covers at most this many days, ending with the period. */
    public const MAX_DAYS = 93;

    /**
     * @param  'day'|'week'|'month'  $unit
     */
    public function __construct(private readonly string $unit) {}

    public function title(): string
    {
        return match ($this->unit) {
            'day' => 'Daily monitoring report',
            'week' => 'Weekly performance report',
            'month' => 'Monthly performance report',
        };
    }

    public function description(): string
    {
        return match ($this->unit) {
            'day' => 'Requests received, accepted, released, overdue and still pending, per day (up to 93 days).',
            'week' => 'On-time releases, overdue cases, average processing time and pending requests, per week.',
            'month' => 'Request volume, on-time percentage and trends, per month.',
        };
    }

    public function columns(): array
    {
        return [ucfirst($this->unit), 'Received', 'Accepted', 'Released', 'Released on time', 'Overdue', 'On-time %', 'Pending at end', 'Avg processing (working days)'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $start = $filter->start;
        $end = $filter->end->min(CarbonImmutable::now()->endOfDay());

        if ($this->unit === 'day' && $start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1)->startOfDay();
        }

        if ($start->greaterThan($end)) {
            return [];
        }

        $received = $this->byDay($filter->requests(), 'submitted_at', $start, $end);
        $accepted = $this->byDay($filter->requests(), 'accepted_at', $start, $end);
        $released = $this->byDay($filter->requests(), 'released_at', $start, $end);
        $onTime = $this->byDay($filter->requests()->whereColumn('released_at', '<=', 'effective_due_at'), 'released_at', $start, $end);
        $overdue = $this->byDay(
            $filter->requests()->whereNotNull('accepted_at')
                ->whereNotIn('status', [RequestStatus::Cancelled, RequestStatus::Declined])
                ->where(fn (Builder $q) => $q->whereNull('released_at')->orWhereColumn('released_at', '>', 'effective_due_at'))
                ->where('effective_due_at', '<=', CarbonImmutable::now()),
            'effective_due_at', $start, $end,
        );
        $closed = $this->byDay($filter->requests(), DB::raw('COALESCE(released_at, declined_at, cancelled_at)'), $start, $end);
        $processing = $filter->requests()->whereBetween('released_at', [$start, $end])->whereNotNull('processing_working_days')
            ->selectRaw('DATE(released_at) as day, SUM(processing_working_days) as total, COUNT(*) as n')
            ->groupBy('day')->get()->keyBy('day');

        $pending = $filter->requests()->where('submitted_at', '<', $start)->count()
            - $filter->requests()->whereRaw('COALESCE(released_at, declined_at, cancelled_at) < ?', [$start])->count();

        $buckets = [];

        for ($day = $start->startOfDay(); $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $key = $this->bucket($day);
            $bucket = $buckets[$key] ?? ['received' => 0, 'accepted' => 0, 'released' => 0, 'onTime' => 0, 'overdue' => 0, 'pending' => 0, 'days' => 0, 'n' => 0];

            $pending += ($received[$date] ?? 0) - ($closed[$date] ?? 0);

            $bucket['received'] += $received[$date] ?? 0;
            $bucket['accepted'] += $accepted[$date] ?? 0;
            $bucket['released'] += $released[$date] ?? 0;
            $bucket['onTime'] += $onTime[$date] ?? 0;
            $bucket['overdue'] += $overdue[$date] ?? 0;
            $bucket['pending'] = $pending;
            $bucket['days'] += (int) ($processing[$date]->total ?? 0);
            $bucket['n'] += (int) ($processing[$date]->n ?? 0);
            $buckets[$key] = $bucket;
        }

        $rows = [];

        foreach ($buckets as $key => $bucket) {
            $judged = $bucket['onTime'] + $bucket['overdue'];
            $rows[] = [
                $key,
                $bucket['received'],
                $bucket['accepted'],
                $bucket['released'],
                $bucket['onTime'],
                $bucket['overdue'],
                $judged === 0 ? null : round($bucket['onTime'] / $judged * 100, 1),
                $bucket['pending'],
                $bucket['n'] === 0 ? null : round($bucket['days'] / $bucket['n'], 1),
            ];
        }

        return $rows;
    }

    /**
     * Count of matching requests per calendar day of a timestamp column.
     *
     * @param  Builder<CredentialRequest>  $query
     * @return array<string, int>
     */
    private function byDay(Builder $query, mixed $column, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $expression = $column instanceof Expression ? $column->getValue(DB::connection()->getQueryGrammar()) : $column;

        return $query
            ->whereRaw("{$expression} between ? and ?", [$start, $end])
            ->selectRaw("DATE({$expression}) as day, COUNT(*) as n")
            ->groupBy('day')
            ->pluck('n', 'day')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function bucket(CarbonImmutable $day): string
    {
        return match ($this->unit) {
            'day' => $day->format('D, M j, Y'),
            'week' => 'Week of '.$day->startOfWeek()->format('M j, Y'),
            'month' => $day->format('F Y'),
        };
    }
}
