<?php

namespace App\Enums;

/**
 * The client's suggested delay categories (Proposed Workflow, section IX-D).
 */
enum DelayCategory: string
{
    case RegistrarEvaluation = 'registrar_evaluation';
    case CustomerCompliance = 'customer_compliance';
    case PaymentVerification = 'payment_verification';
    case AcademicDepartment = 'academic_department';
    case DocumentaryStamp = 'documentary_stamp';
    case PendingSignature = 'pending_signature';
    case DocumentCorrection = 'document_correction';
    case SystemIssue = 'system_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RegistrarEvaluation => 'Registrar / evaluation',
            self::CustomerCompliance => 'Customer compliance',
            self::PaymentVerification => 'Payment verification',
            self::AcademicDepartment => 'Academic department / Dean',
            self::DocumentaryStamp => 'VP Finance documentary stamp',
            self::PendingSignature => 'Pending signature',
            self::DocumentCorrection => 'Document correction',
            self::SystemIssue => 'System or technical issue',
            self::Other => 'Other',
        };
    }

    /**
     * The four causes a delay is classified under (REQ-34-10). The other cases stay only for the
     * reasons recorded before REQ-24-09.
     *
     * @return list<self>
     */
    public static function inferred(): array
    {
        return [self::RegistrarEvaluation, self::PendingSignature, self::DocumentaryStamp, self::DocumentCorrection];
    }

    /** What the cause covers: the tooltip of the dashboard's information icon (REQ-34-03). */
    public function description(): string
    {
        return match ($this) {
            self::RegistrarEvaluation => 'Held at the Office of the Registrar: verifying the records, preparing the credential or the final quality check.',
            self::CustomerCompliance => 'Admission requirements, clearance, affidavit of loss or other documents still to submit',
            self::PaymentVerification => 'Payment not yet confirmed or needs clarification',
            self::AcademicDepartment => 'Pending review, correction or approval',
            self::DocumentaryStamp => 'Held at the Office of the VP for Finance for the documentary stamp.',
            self::PendingSignature => 'Held at the Dean, the Chancellor or the President for a signature.',
            self::DocumentCorrection => 'An office returned the document and it is being corrected internally.',
            self::SystemIssue => 'System downtime or unavailable electronic records',
            self::Other => 'Explained in the reason',
        };
    }
}
