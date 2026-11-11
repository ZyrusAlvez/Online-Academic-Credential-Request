<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * One part of a Registrar stage's checklist (REQ-16-10, 17, 19).
 */
enum CheckItem: string
{
    // Initial Evaluation
    case CredentialRequest = 'credential_request';
    case Identity = 'identity';
    case AcademicRecord = 'academic_record';
    case Representative = 'representative';

    // Payment Validation
    case PaymentProof = 'payment_proof';
    case PaymentAmount = 'payment_amount';

    // Final Quality Check
    case CredentialDocument = 'credential_document';
    case Signatures = 'signatures';

    public function stage(): CheckStage
    {
        return match ($this) {
            self::CredentialRequest, self::Identity, self::AcademicRecord, self::Representative => CheckStage::Evaluation,
            self::PaymentProof, self::PaymentAmount => CheckStage::Payment,
            self::CredentialDocument, self::Signatures => CheckStage::FinalCheck,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CredentialRequest => 'Credential Request Verification',
            self::Identity => 'User Identity Verification',
            self::AcademicRecord => 'Academic Record Verification',
            self::Representative => 'Representative Verification',
            self::PaymentProof => 'Proof of Payment',
            self::PaymentAmount => 'Amount and Reference',
            self::CredentialDocument => 'Credential Document',
            self::Signatures => 'Signatures and Stamps',
        };
    }

    /** What the registrar checks, shown under the part's heading. */
    public function hint(): string
    {
        return match ($this) {
            self::CredentialRequest => 'The credential, purpose and receiving school are right for this requester, with its documentary requirements complete.',
            self::Identity => 'The valid ID is authentic and matches the requester.',
            self::AcademicRecord => "The requester's status, student number, college, program and years match the university's records.",
            self::Representative => 'The SPA and the IDs of the owner and the representative are valid.',
            self::PaymentProof => 'The proof matches a payment in the official payment record.',
            self::PaymentAmount => 'The amount paid covers the amount due, and the reference and date match.',
            self::CredentialDocument => 'The credential is complete and accurate.',
            self::Signatures => 'Every office on the route has signed or stamped it.',
        };
    }

    /**
     * The parts of a stage that apply to a request: the representative part only when the owner
     * transacts through one (REQ-16-10).
     *
     * @return list<self>
     */
    public static function for(CheckStage $stage, CredentialRequest $request): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $item): bool => $item->stage() === $stage
                && ($item !== self::Representative || $request->requester_mode === RequesterMode::Representative),
        ));
    }
}
