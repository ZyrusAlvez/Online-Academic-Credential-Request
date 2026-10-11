<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Current = 'current';
    case Alumni = 'alumni';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current student',
            self::Alumni => 'Alumnus / Alumna',
        };
    }
}
