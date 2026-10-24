<?php

namespace App\Enums;

/**
 * Review outcome of an uploaded document (valid IDs; later SPAs and representative IDs).
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'For verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'sky',
            self::Verified => 'green',
            self::Rejected => 'red',
        };
    }
}
