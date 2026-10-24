<?php

namespace App\Enums;

enum RecipientType: string
{
    case Owner = 'owner';
    case Representative = 'representative';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Document owner',
            self::Representative => 'Authorized representative',
        };
    }
}
