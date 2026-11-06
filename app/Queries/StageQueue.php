<?php

namespace App\Queries;

use App\Enums\RegistrarQueue;
use App\Enums\RequestStatus;
use App\Enums\RouteTask;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use Illuminate\Database\Eloquent\Builder;

/**
 * The requests in each of the registrar's stage queues, oldest first (REQ-16-02..06).
 */
final class StageQueue
{
    /**
     * @param  bool  $waiting  Initial Evaluation only: the requests returned to the requester instead
     * @return Builder<CredentialRequest>
     */
    public static function for(RegistrarQueue $queue, bool $waiting = false, string $office = ''): Builder
    {
        $query = CredentialRequest::query()->with([
            'credentialType:id,name', 'evaluator:id,first_name,middle_name,last_name',
            'openVisit.department:id,name', 'openVisit.step:id,task',
        ]);

        return match ($queue) {
            RegistrarQueue::Evaluation => $query
                ->whereIn('status', $waiting ? [RequestStatus::PendingCompliance] : [RequestStatus::Submitted, RequestStatus::UnderEvaluation])
                ->orderBy('submitted_at')->orderBy('id'),
            RegistrarQueue::Payment => $query
                ->where('status', RequestStatus::PaymentVerification)
                ->orderBy('updated_at')->orderBy('id'),
            RegistrarQueue::InProgress => $query
                ->whereIn('status', [RequestStatus::InProgress, RequestStatus::ReturnedForCorrection])
                ->whereDoesntHave('visits', self::atFinalCheck(...))
                ->when($office !== '', fn (Builder $q) => RegistrarRequestFilter::heldBy($q, (int) $office))
                ->orderBy('effective_due_at')->orderBy('id'),
            RegistrarQueue::FinalCheck => $query
                ->whereIn('status', RequestStatus::processing())
                ->whereHas('visits', self::atFinalCheck(...))
                ->orderBy('effective_due_at')->orderBy('id'),
            RegistrarQueue::Release => $query
                ->where('status', RequestStatus::ReadyForRelease)
                ->orderBy('ready_at')->orderBy('id'),
        };
    }

    /**
     * An open visit of the Registrar's final quality check (not a correction sent from it).
     *
     * @param  Builder<OfficeVisit>  $visits
     */
    private static function atFinalCheck(Builder $visits): void
    {
        $visits->whereIn('status', [VisitStatus::InTransit, VisitStatus::Received])
            ->where('purpose', '!=', VisitPurpose::Correction)
            ->whereHas('step', fn (Builder $q) => $q->where('task', RouteTask::FinalCheck));
    }
}
