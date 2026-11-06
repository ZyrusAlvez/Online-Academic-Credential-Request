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
            self::Evaluation => 'New and resubmitted requests to evaluate. Once you start one, it is yours until you decide.',
            self::Payment => 'Proofs of payment to validate against the official payment record.',
            self::InProgress => 'Where each document is now, between the offices on its route.',
            self::FinalCheck => 'Documents back at the Office of the Registrar for the final quality check.',
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
