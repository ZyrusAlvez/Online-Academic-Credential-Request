<?php

namespace App\Enums;

/**
 * One physical stay of the document at an office (spec 05).
 */
enum VisitStatus: string
{
    case InTransit = 'in_transit';
    case Received = 'received';
    case Completed = 'completed';
    case Returned = 'returned';
    case Bypassed = 'bypassed';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => 'Forwarded, awaiting receipt',
            self::Received => 'In office',
            self::Completed => 'Completed',
            self::Returned => 'Returned for correction',
            self::Bypassed => 'Bypassed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InTransit => 'sky',
            self::Received => 'blue',
            self::Completed => 'green',
            self::Returned => 'orange',
            self::Bypassed => 'zinc',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::InTransit || $this === self::Received;
    }
}
