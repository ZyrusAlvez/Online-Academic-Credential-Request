<?php

namespace App\Enums;

/**
 * The client's recommended request statuses (Proposed Workflow, section V).
 */
enum RequestStatus: string
{
    case Submitted = 'submitted';
    case UnderEvaluation = 'under_evaluation';
    case PendingCompliance = 'pending_compliance';
    case ReturnedForCustomerAction = 'returned_for_customer_action';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentVerification = 'payment_verification';
    case AcceptedForProcessing = 'accepted_for_processing';
    case InProgress = 'in_progress';
    case ReturnedForCorrection = 'returned_for_correction';
    case ForFinalApproval = 'for_final_approval';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderEvaluation => 'Under evaluation',
            self::PendingCompliance => 'Pending compliance',
            self::ReturnedForCustomerAction => 'Returned for customer action',
            self::AwaitingPayment => 'Awaiting payment',
            self::PaymentVerification => 'Payment verification',
            self::AcceptedForProcessing => 'Accepted for processing',
            self::InProgress => 'In progress',
            self::ReturnedForCorrection => 'Returned for correction',
            self::ForFinalApproval => 'For final approval',
            self::ReadyForRelease => 'Ready for release',
            self::Released => 'Released',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * What the requester sees: an internal return between offices is still "In progress".
     */
    public function customerLabel(): string
    {
        return $this === self::ReturnedForCorrection ? self::InProgress->label() : $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted, self::UnderEvaluation, self::PaymentVerification => 'sky',
            self::PendingCompliance, self::ReturnedForCustomerAction, self::AwaitingPayment => 'amber',
            self::AcceptedForProcessing, self::InProgress, self::ForFinalApproval => 'blue',
            self::ReturnedForCorrection => 'orange',
            self::ReadyForRelease => 'green',
            self::Released => 'emerald',
            self::Declined => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Released, self::Declined, self::Cancelled], true);
    }

    /**
     * Not yet accepted: the processing period has not started.
     */
    public function isBeforeAcceptance(): bool
    {
        return in_array($this, self::beforeAcceptance(), true);
    }

    /**
     * @return list<self>
     */
    public static function beforeAcceptance(): array
    {
        return [
            self::Submitted, self::UnderEvaluation, self::PendingCompliance,
            self::AwaitingPayment, self::PaymentVerification,
        ];
    }

    /**
     * Accepted and moving through the offices: the processing period is running.
     *
     * @return list<self>
     */
    public static function processing(): array
    {
        return [self::AcceptedForProcessing, self::InProgress, self::ReturnedForCorrection, self::ForFinalApproval];
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! $status->isFinal()));
    }
}
