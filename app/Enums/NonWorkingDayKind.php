<?php

namespace App\Enums;

enum NonWorkingDayKind: string
{
    case Holiday = 'holiday';
    case Special = 'special';
    case Closure = 'closure';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Regular holiday',
            self::Special => 'Special non-working day',
            self::Closure => 'Office closure',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Holiday => 'red',
            self::Special => 'amber',
            self::Closure => 'zinc',
        };
    }
}
