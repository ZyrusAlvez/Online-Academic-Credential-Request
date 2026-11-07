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
            'openVisit.department:id,name,code', 'openVisit.fromDepartment:id,name', 'openVisit.step:id,task,position,department_id',
        ]);

        return match ($queue) {
            RegistrarQueue::Evaluation => $query
                ->whereIn('status', $waiting ? [RequestStatus::PendingCompliance] : [RequestStatus::Submitted, RequestStatus::UnderEvaluation])
                ->orderBy('submitted_at')->orderBy('id'),
            RegistrarQueue::Payment => $query
                ->where('status', RequestStatus::PaymentVerification)
                ->orderBy('updated_at')->orderBy('id'),
            // Where each paper is and goes next, from acceptance on (REQ-17-11).
            RegistrarQueue::InProgress => $query
                ->with(['steps:id,credential_request_id,department_id,position,status', 'steps.department:id,name'])
                ->whereIn('status', [RequestStatus::AcceptedForProcessing, RequestStatus::InProgress, RequestStatus::ReturnedForCorrection])
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
        $visits->whereIn('status', VisitStatus::open())
            ->where('purpose', '!=', VisitPurpose::Correction)
            ->whereHas('step', fn (Builder $q) => $q->where('task', RouteTask::FinalCheck));
    }
}
