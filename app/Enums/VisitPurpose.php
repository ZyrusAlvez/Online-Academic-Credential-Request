<?php

namespace App\Enums;

enum VisitPurpose: string
{
    /** The office's own step on the route. */
    case Route = 'route';

    /** Sent back to an earlier office to be corrected. */
    case Correction = 'correction';

    /** Sent back, corrected, to the office that returned it. */
    case Resubmission = 'resubmission';

    public function label(): string
    {
        return match ($this) {
            self::Route => 'Route',
            self::Correction => 'Correction',
            self::Resubmission => 'Resubmission',
        };
    }
}
