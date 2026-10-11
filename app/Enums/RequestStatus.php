<?php

namespace App\Enums;

enum RequestStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentReview = 'payment_review';
    case RegistrarReview = 'registrar_review';
    case InProgress = 'in_progress';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::PaymentReview => 'Payment under review',
            self::RegistrarReview => 'Under review',
            self::InProgress => 'In progress',
            self::ReadyForRelease => 'Ready for release',
            self::Released => 'Released',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Flux badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'amber',
            self::PaymentReview, self::RegistrarReview => 'sky',
            self::InProgress => 'blue',
            self::ReadyForRelease => 'green',
            self::Released => 'emerald',
            self::Cancelled => 'zinc',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Released, self::Cancelled], true);
    }

    /** Statuses in which the request has not yet been routed to departments. */
    public function isBeforeRouting(): bool
    {
        return in_array($this, [self::AwaitingPayment, self::PaymentReview, self::RegistrarReview], true);
    }

    public function needsRegistrarReview(): bool
    {
        return in_array($this, [self::PaymentReview, self::RegistrarReview], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }
}
