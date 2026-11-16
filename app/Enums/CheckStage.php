<?php

namespace App\Enums;

/**
 * The Registrar stages that end with a checklist and a decision (spec 16): Initial Evaluation only,
 * since Payment Validation's checklist was removed (REQ-16-17, 2026-11-16).
 */
enum CheckStage: string
{
    case Evaluation = 'evaluation';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Initial Evaluation',
        };
    }
}
