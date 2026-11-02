<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'For verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::Verified => 'green',
            self::Rejected => 'red',
        };
    }
}
