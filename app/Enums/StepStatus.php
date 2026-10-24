<?php

namespace App\Enums;

/**
 * A route step of a request (spec 05).
 */
enum StepStatus: string
{
    case Waiting = 'waiting';
    case Current = 'current';
    case Completed = 'completed';
    case Bypassed = 'bypassed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Current => 'Current',
            self::Completed => 'Completed',
            self::Bypassed => 'Bypassed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'zinc',
            self::Current => 'blue',
            self::Completed => 'green',
            self::Bypassed => 'zinc',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Completed || $this === self::Bypassed;
    }
}
