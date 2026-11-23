<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * The step an Admin override records for a credential: the one it is waiting for, on behalf of
 * whoever couldn't record it (spec 35). Judgment calls are never offered (REQ-35-06).
 */
enum OverrideStep: string
{
    case Receive = 'receive';
    case Approve = 'approve';
    case Forward = 'forward';
    case FinalCheck = 'final_check';
    case ValidatePayment = 'validate_payment';
    case Release = 'release';
    case Unlock = 'unlock';

    /**
     * The step the credential is waiting for, or null when there is none to record
     * (REQ-35-02..06). Before acceptance it is the application's, which its first credential
     * carries (REQ-27-03).
     */
    public static function for(CredentialRequest $request): ?self
    {
        return match ($request->status) {
            RequestStatus::UnderEvaluation => self::Unlock,
            RequestStatus::PaymentVerification => self::ValidatePayment,
            RequestStatus::ReadyForRelease => self::Release,
            RequestStatus::AcceptedForProcessing, RequestStatus::InProgress,
            RequestStatus::ReturnedForCorrection, RequestStatus::ForFinalApproval => self::forPaper($request),
            default => null,
        };
    }

    /** What the paper at its office is waiting for (REQ-35-02). */
    private static function forPaper(CredentialRequest $request): ?self
    {
        $visit = $request->loadMissing('openVisit.department', 'openVisit.step')->openVisit;

        return match (true) {
            $visit === null => null,
            $visit->status === VisitStatus::InTransit => self::Receive,
            $request->isAtFinalCheck() => self::FinalCheck,
            $visit->isForwardable() => self::Forward,
            // Received at an office other than the Registrar's, which forwards its own (REQ-17-12).
            $visit->status === VisitStatus::Received => self::Approve,
            default => null,
        };
    }

    /** The dialog's heading, naming the office (REQ-35-02). */
    public function label(CredentialRequest $request): string
    {
        $request->loadMissing('openVisit.department', 'openVisit.step', 'steps.department');
        $office = $request->openVisit?->department->name;

        return match ($this) {
            self::Receive => "Accept receipt at {$office}",
            self::Approve => $request->openVisit?->purpose === VisitPurpose::Correction
                ? "Approve the correction at {$office}"
                : "Approve at {$office}",
            self::Forward => 'Forward to '.$request->nextOffice()?->name,
            self::FinalCheck => 'Approve the final quality check',
            self::ValidatePayment => 'Validate payment',
            self::Release => 'Mark released',
            self::Unlock => 'Unlock the evaluation',
        };
    }

    /** The dialog's button. */
    public function button(): string
    {
        return match ($this) {
            self::Receive => 'Accept receipt',
            self::Approve, self::FinalCheck => 'Approve',
            self::Forward => 'Forward',
            self::ValidatePayment => 'Validate payment',
            self::Release => 'Mark released',
            self::Unlock => 'Unlock',
        };
    }
}
