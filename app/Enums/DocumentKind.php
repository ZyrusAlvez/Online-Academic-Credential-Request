<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Spa = 'spa';
    case RepresentativeId = 'representative_id';

    /** A copy of one of the credential's documentary requirements (REQ-04-18). */
    case Requirement = 'requirement';

    public function label(): string
    {
        return match ($this) {
            self::Spa => 'Special Power of Attorney',
            self::RepresentativeId => "Representative's valid ID",
            self::Requirement => 'Documentary requirement',
        };
    }
}
