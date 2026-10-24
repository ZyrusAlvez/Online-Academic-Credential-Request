<?php

namespace App\Enums;

/**
 * Whether the Registrar has confirmed a requester's valid ID (spec 01).
 */
enum IdentityStatus: string
{
    case Unsubmitted = 'unsubmitted';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unsubmitted => 'No ID uploaded',
            self::Pending => 'ID awaiting verification',
            self::Verified => 'Identity verified',
            self::Rejected => 'ID rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unsubmitted => 'zinc',
            self::Pending => 'sky',
            self::Verified => 'green',
            self::Rejected => 'red',
        };
    }
}
