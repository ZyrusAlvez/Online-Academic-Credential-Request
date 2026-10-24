<?php

namespace App\Enums;

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
     * Entries requesters never see: hand-offs between offices, internal returns, delay notes
     * and bypasses (REQ-06-02).
     */
    public function isInternal(): bool
    {
        return in_array($this, [
            self::DocumentForwarded,
            self::DocumentReturned,
            self::DocumentResubmitted,
            self::DelayRecorded,
            self::StepBypassed,
        ], true);
    }
}
