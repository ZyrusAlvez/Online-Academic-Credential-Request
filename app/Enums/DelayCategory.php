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

    public function description(): string
    {
        return match ($this) {
            self::RegistrarEvaluation => 'Academic record verification, incomplete information or document preparation',
            self::CustomerCompliance => 'Missing admission requirements, clearance, affidavit of loss or other documents',
            self::PaymentVerification => 'Payment not yet confirmed or needs clarification',
            self::AcademicDepartment => 'Pending review, correction or approval',
            self::DocumentaryStamp => 'Documentary stamp processing or related clarification',
            self::PendingSignature => 'Dean, Chancellor or President signature pending',
            self::DocumentCorrection => 'Errors in the document, academic details or remarks',
            self::SystemIssue => 'System downtime or unavailable electronic records',
            self::Other => 'Explained in the reason',
        };
    }
}
