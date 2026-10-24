<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Evaluator performance (REQ-07-15): per registrar evaluator, the requests taken up in the
 * period, their outcomes, the initial evaluation time and the open workload now.
 */
final class EvaluatorReport implements Report
{
    public function title(): string
    {
        return 'Evaluator performance report';
    }

    public function description(): string
    {
        return 'Requests evaluated, pending evaluations, turnaround time and workload, per evaluator.';
    }

    public function columns(): array
    {
        return ['Evaluator', 'Taken up', 'Requirements complete', 'Sent for compliance', 'Declined', 'Avg initial evaluation (hours)', 'Open workload now'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $taken = $filter->requests()
            ->whereNotNull('evaluated_by')
            ->whereBetween('evaluation_started_at', [$filter->start, $filter->end])
            ->groupBy('evaluated_by')
            ->get([
                'evaluated_by',
                DB::raw('COUNT(*) as taken'),
                DB::raw('SUM(requirements_completed_at IS NOT NULL) as completed'),
                DB::raw('SUM(EXISTS (SELECT 1 FROM request_deficiencies d WHERE d.credential_request_id = credential_requests.id)) as compliance'),
                DB::raw("SUM(status = '".RequestStatus::Declined->value."') as declined"),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, submitted_at, evaluation_started_at)) as initial'),
            ])
            ->keyBy('evaluated_by');

        $workload = $filter->requests()
            ->whereNotNull('evaluated_by')
            ->whereNull('accepted_at')
            ->whereIn('status', [RequestStatus::UnderEvaluation, RequestStatus::PendingCompliance, RequestStatus::ReturnedForCustomerAction])
            ->groupBy('evaluated_by')
            ->pluck(DB::raw('COUNT(*)'), 'evaluated_by');

        $ids = $taken->keys()->merge($workload->keys())->unique();
        $names = User::whereIn('id', $ids)->get()->mapWithKeys(fn (User $user) => [$user->id => $user->name]);

        return $ids->map(function ($id) use ($taken, $workload, $names): array {
            $row = $taken->get($id);

            return [
                $names[$id] ?? "#{$id}",
                (int) ($row?->getAttribute('taken') ?? 0),
                (int) ($row?->getAttribute('completed') ?? 0),
                (int) ($row?->getAttribute('compliance') ?? 0),
                (int) ($row?->getAttribute('declined') ?? 0),
                $row?->getAttribute('initial') === null ? null : round((float) $row->getAttribute('initial') / 60, 1),
                (int) ($workload[$id] ?? 0),
            ];
        })->sortBy(fn (array $row): string => (string) $row[0])->values()->all();
    }
}
