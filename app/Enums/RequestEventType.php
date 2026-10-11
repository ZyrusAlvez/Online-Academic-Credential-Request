<?php

namespace App\Enums;

enum RequestEventType: string
{
    case Submitted = 'submitted';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentRejected = 'payment_rejected';
    case PaymentAccepted = 'payment_accepted';
    case CollegeChanged = 'college_changed';
    case Routed = 'routed';
    case StepSigned = 'step_signed';
    case StepHeld = 'step_held';
    case StepResumed = 'step_resumed';
    case StepSkipped = 'step_skipped';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Request submitted',
            self::PaymentSubmitted => 'Proof of payment uploaded',
            self::PaymentRejected => 'Payment rejected',
            self::PaymentAccepted => 'Payment verified',
            self::CollegeChanged => 'College corrected',
            self::Routed => 'Approved and routed to offices',
            self::StepSigned => 'Signed',
            self::StepHeld => 'Put on hold',
            self::StepResumed => 'Hold lifted',
            self::StepSkipped => 'Step skipped by the Registrar',
            self::ReadyForRelease => 'Ready for release',
            self::Released => 'Released to the student',
            self::Cancelled => 'Request cancelled',
        };
    }
}
