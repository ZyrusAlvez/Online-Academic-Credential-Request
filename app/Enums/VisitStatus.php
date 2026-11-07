<?php

namespace App\Enums;

/**
 * One physical stay of the document at an office (spec 05): forwarded, received, done (the task
 * is finished but the paper is still there), then completed when it leaves (spec 17).
 */
enum VisitStatus: string
{
    case InTransit = 'in_transit';
    case Received = 'received';
    case Done = 'done';
    case Completed = 'completed';
    case Returned = 'returned';
    case Bypassed = 'bypassed';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => 'Forwarded, awaiting receipt',
            self::Received => 'In office',
            self::Done => 'Done, not yet forwarded',
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
            self::Done => 'indigo',
            self::Completed => 'green',
            self::Returned => 'orange',
            self::Bypassed => 'zinc',
        };
    }

    /**
     * The office holds the paper, or it is on its way there.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::InTransit, self::Received, self::Done];
    }

    /**
     * The paper is physically in the office (REQ-17-15).
     *
     * @return list<self>
     */
    public static function inOffice(): array
    {
        return [self::Received, self::Done];
    }
}
