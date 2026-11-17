<?php

namespace App\Enums;

enum AcademicStatus: string
{
    case Current = 'current';
    case Graduate = 'graduate';
    case Former = 'former';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Currently Enrolled',
            self::Graduate => 'Graduated',
            self::Former => 'Former student',
        };
    }
}
