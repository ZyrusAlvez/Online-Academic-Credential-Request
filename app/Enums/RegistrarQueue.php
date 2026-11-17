<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * The registrar's stage queues (REQ-16-01..06).
 */
enum RegistrarQueue: string
{
    case Evaluation = 'evaluation';
    case Payment = 'payment';
    case InProgress = 'in_progress';
    case Outgoing = 'outgoing';
    case FinalCheck = 'final_check';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Initial Evaluation',
            self::Payment => 'Payment Validation',
            self::InProgress => 'In Progress',
            self::Outgoing => 'Outgoing',
            self::FinalCheck => 'Final Quality Check',
            self::Release => 'Release',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Evaluation => 'Requests to evaluate.',
            self::Payment => 'Walk-in payments to validate.',
            self::InProgress => 'Where each document is and where it goes next.',
            self::Outgoing => 'Documents to collect and bring to the next office.',
            self::FinalCheck => 'Documents back for the final check.',
            self::Release => 'Credentials ready to be claimed.',
        };
    }

    /**
     * Queues with a second tab for the requests waiting for the requester (REQ-16-02). Walk-in
     * payments never wait for the requester; the cashier's list has its own (REQ-25-04, 07).
     */
    public function hasWaitingTab(): bool
    {
        return $this === self::Evaluation;
    }

    /** The first tab's name, beside "Waiting for the requester". */
    public function activeTabLabel(): string
    {
        return $this === self::Payment ? 'To verify' : 'To evaluate';
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Evaluation => 'registrar.evaluation',
            self::Payment => 'registrar.payments',
            self::InProgress => 'registrar.in-progress',
            self::Outgoing => 'registrar.outgoing',
            self::FinalCheck => 'registrar.final-check',
            self::Release => 'registrar.release',
        };
    }

    /** In Progress and Outgoing are lists only; their rows act in place (REQ-17-13, REQ-32-02). */
    public function hasWorkingPage(): bool
    {
        return $this !== self::InProgress && $this !== self::Outgoing;
    }

    /** The lists of papers between offices, with the office filter (REQ-17-11, REQ-32-02). */
    public function tracksOffices(): bool
    {
        return $this === self::InProgress || $this === self::Outgoing;
    }

    /** The request's working page under this queue (REQ-16-26); see hasWorkingPage(). */
    public function showRouteName(): string
    {
        return $this->routeName().'.show';
    }

    /**
     * The queue a request is in now, as `StageQueue::for()` lists them, or null once it is closed
     * (released, declined, cancelled) or while its payment is the cashier's (REQ-16-26, REQ-25-07).
     */
    public static function forRequest(CredentialRequest $request): ?self
    {
        $status = $request->status;

        return match (true) {
            in_array($status, [RequestStatus::Submitted, RequestStatus::UnderEvaluation, RequestStatus::ReturnedForCustomerAction, ...RequestStatus::waitingForRequester()], true) => self::Evaluation,
            in_array($status, [RequestStatus::AwaitingPayment, RequestStatus::PaymentVerification], true) => $request->isWalkIn() ? self::Payment : null,
            in_array($status, RequestStatus::processing(), true) => match (true) {
                $request->isAtFinalCheck() => self::FinalCheck,
                $request->loadMissing('openVisit.department', 'openVisit.step')->openVisit?->isForwardable() === true => self::Outgoing,
                default => self::InProgress,
            },
            $status === RequestStatus::ReadyForRelease => self::Release,
            default => null,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Evaluation => 'clipboard-document-check',
            self::Payment => 'credit-card',
            self::InProgress => 'arrow-path-rounded-square',
            self::Outgoing => 'arrow-up-tray',
            self::FinalCheck => 'shield-check',
            self::Release => 'hand-raised',
        };
    }
}
