<?php

namespace App\Queries;

use App\Enums\RegistrarQueue;
use App\Enums\RequestChannel;
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
     * @param  bool  $waiting  Initial Evaluation and Payment Validation: the requests waiting for the
     *                         requester instead (returned, or awaiting payment)
     * @param  string  $search  tracking number or requester name, as on Records (REQ-16-25)
     * @param  RequestChannel  $channel  Payment Validation: walk-in requests are the Registrar's,
     *                                   online ones the Cashier's (REQ-25-04, 07)
     * @return Builder<CredentialRequest>
     */
    public static function for(RegistrarQueue $queue, bool $waiting = false, string $office = '', string $search = '', RequestChannel $channel = RequestChannel::WalkIn): Builder
    {
        $query = RegistrarRequestFilter::matching(CredentialRequest::query(), $search)->with([
            'credentialType:id,name', 'evaluator:id,first_name,middle_name,last_name,email',
            'openVisit.department:id,name,code', 'openVisit.fromDepartment:id,name', 'openVisit.step:id,task,position,department_id',
        ]);

        // Until acceptance an application is one row, its first credential (REQ-27-03).
        if (in_array($queue, [RegistrarQueue::Evaluation, RegistrarQueue::Payment], true)) {
            $query->where('item_no', 1);
        }

        return match ($queue) {
            RegistrarQueue::Evaluation => $query
                ->whereIn('status', $waiting ? RequestStatus::waitingForRequester() : [RequestStatus::Submitted, RequestStatus::UnderEvaluation])
                ->orderBy('submitted_at')->orderBy('id'),
            // Proofs to verify; the requests still awaiting payment wait apart (REQ-16-03).
            RegistrarQueue::Payment => $query
                ->where('channel', $channel)
                ->where('status', $waiting ? RequestStatus::AwaitingPayment : RequestStatus::PaymentVerification)
                ->orderBy('updated_at')->orderBy('id'),
            // The papers an office finished, waiting for the Registrar to collect (REQ-32-02).
            RegistrarQueue::Outgoing => $query
                ->with(['steps:id,credential_request_id,department_id,position,status', 'steps.department:id,name', 'openVisit.returnedTo:id,name'])
                ->whereIn('status', [RequestStatus::AcceptedForProcessing, RequestStatus::InProgress, RequestStatus::ReturnedForCorrection])
                ->whereDoesntHave('visits', self::atFinalCheck(...))
                ->whereHas('visits', self::forwardable(...))
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
     * An open visit whose paper the Registrar can collect and forward (REQ-32-02).
     *
     * @param  Builder<OfficeVisit>  $visits
     */
    private static function forwardable(Builder $visits): void
    {
        $visits->whereIn('status', VisitStatus::open());
        OfficeVisit::whereForwardable($visits);
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
