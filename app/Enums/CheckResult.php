<?php

namespace App\Enums;

/**
 * The outcome of one checklist part (REQ-16-10).
 */
enum CheckResult: string
{
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'green',
            self::Rejected => 'red',
        };
    }
}
