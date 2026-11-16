<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * One part of Initial Evaluation's checklist (REQ-16-10). The final quality check (REQ-16-19) and
 * Payment Validation (REQ-16-17) have none.
 */
enum CheckItem: string
{
    case CredentialRequest = 'credential_request';
    case Identity = 'identity';
    case AcademicRecord = 'academic_record';
    case Representative = 'representative';

    public function stage(): CheckStage
    {
        return CheckStage::Evaluation;
    }

    public function label(): string
    {
        return match ($this) {
            self::CredentialRequest => 'Credential Request Verification',
            self::Identity => 'User Identity Verification',
            self::AcademicRecord => 'Academic Record Verification',
            self::Representative => 'Representative Verification',
        };
    }

    /** What the registrar checks, shown under the part's heading. */
    public function hint(): string
    {
        return match ($this) {
            self::CredentialRequest => 'The credential, purpose and receiving school are right for this requester.',
            self::Identity => 'The valid ID is authentic and matches the requester.',
            self::AcademicRecord => "The requester's status, student number, college, program and years match the university's records.",
            self::Representative => 'The SPA and the IDs of the owner and the representative are valid.',
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
        return match ($stage) {
            CheckStage::Evaluation => $request->requester_mode === RequesterMode::Representative
                ? self::cases()
                : [self::CredentialRequest, self::Identity, self::AcademicRecord],
        };
    }
}
