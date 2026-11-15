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
    case FinalCheck = 'final_check';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Initial Evaluation',
            self::Payment => 'Payment Validation',
            self::InProgress => 'In Progress',
            self::FinalCheck => 'Final Quality Check',
            self::Release => 'Release',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Evaluation => 'Requests to evaluate.',
            self::Payment => 'Proofs of payment to verify.',
            self::InProgress => 'Where each document is and where it goes next.',
            self::FinalCheck => 'Documents back for the final check.',
            self::Release => 'Credentials ready to be claimed.',
        };
    }

    /** Queues with a second tab for the requests waiting for the requester (REQ-16-02, 03). */
    public function hasWaitingTab(): bool
    {
        return $this === self::Evaluation || $this === self::Payment;
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
            self::FinalCheck => 'registrar.final-check',
            self::Release => 'registrar.release',
        };
    }

    /** In Progress is a list only; its rows act in place (REQ-17-13). */
    public function hasWorkingPage(): bool
    {
        return $this !== self::InProgress;
    }

    /** The request's working page under this queue (REQ-16-26); see hasWorkingPage(). */
    public function showRouteName(): string
    {
        return $this->routeName().'.show';
    }

    /**
     * The queue a request is in now, as `StageQueue::for()` lists them, or null once it is closed
     * (released, declined, cancelled) (REQ-16-26).
     */
    public static function forRequest(CredentialRequest $request): ?self
    {
        $status = $request->status;

        return match (true) {
            in_array($status, [RequestStatus::Submitted, RequestStatus::UnderEvaluation, RequestStatus::ReturnedForCustomerAction, ...RequestStatus::waitingForRequester()], true) => self::Evaluation,
            in_array($status, [RequestStatus::AwaitingPayment, RequestStatus::PaymentVerification], true) => self::Payment,
            in_array($status, RequestStatus::processing(), true) => $request->isAtFinalCheck() ? self::FinalCheck : self::InProgress,
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
            self::FinalCheck => 'shield-check',
            self::Release => 'hand-raised',
        };
    }
}
