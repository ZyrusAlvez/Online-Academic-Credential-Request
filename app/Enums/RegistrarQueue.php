<?php

namespace App\Enums;

/**
 * The registrar's stage queues (REQ-16-01..06).
 */
enum RegistrarQueue: string
{
    case Evaluation = 'evaluation';
    case Payment = 'payment';
    case InProgress = 'in_progress';
    case FinalCheck = 'final_check';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Initial Evaluation',
            self::Payment => 'Payment Validation',
            self::InProgress => 'In Progress',
            self::FinalCheck => 'Final Quality Check',
            self::Release => 'Release',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Evaluation => 'Requests to evaluate.',
            self::Payment => 'Proofs of payment to verify.',
            self::InProgress => 'Where each document is and where it goes next.',
            self::FinalCheck => 'Documents back for the final check.',
            self::Release => 'Credentials ready to be claimed.',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Evaluation => 'registrar.evaluation',
            self::Payment => 'registrar.payments',
            self::InProgress => 'registrar.in-progress',
            self::FinalCheck => 'registrar.final-check',
            self::Release => 'registrar.release',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Evaluation => 'clipboard-document-check',
            self::Payment => 'credit-card',
            self::InProgress => 'arrow-path-rounded-square',
            self::FinalCheck => 'shield-check',
            self::Release => 'hand-raised',
        };
    }
}
