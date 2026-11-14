<?php

namespace App\Enums;

use App\Models\CredentialRequest;

/**
 * Kinds of ledger entries (request_events). Every action on a request writes one.
 */
enum RequestEventType: string
{
    // Submission and evaluation (spec 04)
    case Submitted = 'submitted';
    case EvaluationStarted = 'evaluation_started';
    case ComplianceRequested = 'compliance_requested';
    case DeficiencyResolved = 'deficiency_resolved';
    case CustomerActionRequested = 'customer_action_requested';
    case CustomerResponded = 'customer_responded';
    case RequirementsCompleted = 'requirements_completed';
    case Declined = 'declined';
    case CollegeChanged = 'college_changed';

    // Registrar stages (spec 16); evaluation_reopened is in older ledgers only (spec 20)
    case Resubmitted = 'resubmitted';
    case EvaluationReopened = 'evaluation_reopened';

    // Edit Request (spec 20)
    case CredentialChanged = 'credential_changed';

    // The request's valid ID (spec 15)
    case IdentityVerified = 'identity_verified';
    case IdentityRejected = 'identity_rejected';
    case IdentityResubmitted = 'identity_resubmitted';

    // Payment and acceptance (spec 04)
    case PaymentSubmitted = 'payment_submitted';
    case PaymentRejected = 'payment_rejected';
    case PaymentVerified = 'payment_verified';
    case Accepted = 'accepted';

    // Office routing (spec 05)
    case DocumentForwarded = 'document_forwarded';
    case ReceiptAccepted = 'receipt_accepted';
    case StepCompleted = 'step_completed';
    case DocumentReturned = 'document_returned';
    case DocumentResubmitted = 'document_resubmitted';
    case Corrected = 'corrected';
    case DelayRecorded = 'delay_recorded';
    case StepBypassed = 'step_bypassed';
    case ReadyForRelease = 'ready_for_release';

    // Representatives and release (spec 11)
    case RepresentativeDeclared = 'representative_declared';
    case RepresentativeVerified = 'representative_verified';
    case RepresentativeRejected = 'representative_rejected';
    case Released = 'released';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * Whether this entry asks the requester to do something: correct the request, visit the
     * office, upload another ID or proof, pay, name another representative, or claim the
     * credential. Their notifications are marked [ACTION REQUIRED] (REQ-06-18).
     */
    public function requiresRequesterAction(CredentialRequest $request): bool
    {
        return match ($this) {
            self::CustomerActionRequested, self::ComplianceRequested, self::IdentityRejected,
            self::PaymentRejected, self::RepresentativeRejected, self::ReadyForRelease => true,
            self::RequirementsCompleted => (float) $request->amount_due > 0,
            default => false,
        };
    }

    /**
     * Entries requesters never see: hand-offs between offices, internal returns, delay notes
     * and bypasses (REQ-06-02).
     */
    public function isInternal(): bool
    {
        return in_array($this, [
            self::DocumentForwarded,
            self::DocumentReturned,
            self::DocumentResubmitted,
            self::Corrected,
            self::DelayRecorded,
            self::StepBypassed,
        ], true);
    }
}
