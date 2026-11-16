<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Spa = 'spa';
    case RepresentativeId = 'representative_id';

    public function label(): string
    {
        return match ($this) {
            self::Spa => 'Special Power of Attorney',
            self::RepresentativeId => "Representative's valid ID",
        };
    }
}
