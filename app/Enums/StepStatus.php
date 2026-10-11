<?php

namespace App\Enums;

enum StepStatus: string
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case OnHold = 'on_hold';
    case Signed = 'signed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Pending => 'For signature',
            self::OnHold => 'On hold',
            self::Signed => 'Signed',
            self::Skipped => 'Skipped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'zinc',
            self::Pending => 'blue',
            self::OnHold => 'amber',
            self::Signed => 'green',
            self::Skipped => 'zinc',
        };
    }

    /** The step currently holding the document. */
    public function isCurrent(): bool
    {
        return in_array($this, [self::Pending, self::OnHold], true);
    }

    public function isDone(): bool
    {
        return in_array($this, [self::Signed, self::Skipped], true);
    }
}
