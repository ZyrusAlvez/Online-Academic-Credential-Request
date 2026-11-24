<?php

namespace App\Enums;

/**
 * A requester's sex, asked on the Personal Information section (REQ-28-03).
 */
enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
