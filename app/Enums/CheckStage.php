<?php

namespace App\Enums;

/**
 * The Registrar stages that end with a checklist and a decision (spec 16).
 */
enum CheckStage: string
{
    case Evaluation = 'evaluation';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Initial Evaluation',
            self::Payment => 'Payment Validation',
        };
    }
}
