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

    /**
     * The people of this status, as in "Free for graduates" (REQ-29-08).
     */
    public function groupLabel(): string
    {
        return match ($this) {
            self::Current => 'currently enrolled students',
            self::Graduate => 'graduates',
            self::Former => 'former students',
        };
    }
}
