<?php

namespace App\Enums;

/**
 * Whether the owner transacts personally or through an authorized representative (spec 11).
 */
enum RequesterMode: string
{
    case Personal = 'personal';
    case Representative = 'representative';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personally',
            self::Representative => 'Through an authorized representative',
        };
    }
}
