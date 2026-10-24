<?php

namespace App\Models;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only ledger entry. Rows can be created but never updated or deleted.
 *
 * @property RequestEventType $type
 * @property ?RequestStatus $from_status
 * @property ?RequestStatus $to_status
 * @property Carbon $created_at
 */
class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'actor_id',
        'type',
        'from_status',
        'to_status',
        'request_step_id',
        'office_visit_id',
        'department_id',
        'remarks',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Request events are append-only.'));
        static::deleting(fn () => throw new LogicException('Request events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RequestEventType::class,
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * One-line description used by the timeline, notifications and emails; names offices, never
     * staff. Expects `department` to be loaded for office events.
     */
    public function summary(): string
    {
        $office = $this->department->name ?? 'an office';

        return match ($this->type) {
            RequestEventType::Submitted => 'Request received for evaluation',
            RequestEventType::EvaluationStarted => 'Evaluation started by the Office of the Registrar',
            RequestEventType::ComplianceRequested => 'Requirements incomplete – please comply in person',
            RequestEventType::DeficiencyResolved => 'Requirement complied with',
            RequestEventType::CustomerActionRequested => 'Your action is needed',
            RequestEventType::CustomerResponded => 'Customer action completed',
            RequestEventType::RequirementsCompleted => 'Requirements complete',
            RequestEventType::Declined => 'Request declined',
            RequestEventType::CollegeChanged => 'College corrected by the Registrar',
            RequestEventType::PaymentSubmitted => 'Proof of payment uploaded',
            RequestEventType::PaymentRejected => 'Payment not accepted – please upload a valid proof',
            RequestEventType::PaymentVerified => 'Payment verified',
            RequestEventType::Accepted => 'Accepted for processing',
            RequestEventType::DocumentForwarded => "Forwarded to {$office}",
            RequestEventType::ReceiptAccepted => "Received by {$office}",
            RequestEventType::StepCompleted => $this->step !== null
                ? "{$this->step->task->doneLabel()} by {$office}"
                : "Completed by {$office}",
            RequestEventType::DocumentReturned => "Returned for correction by {$office}",
            RequestEventType::DocumentResubmitted => "Corrected and sent back by {$office}",
            RequestEventType::DelayRecorded => "Delay recorded at {$office}",
            RequestEventType::StepBypassed => "{$office} step bypassed by the Registrar",
            RequestEventType::ReadyForRelease => 'Ready for release at the Office of the Registrar',
            RequestEventType::RepresentativeDeclared => 'Authorized representative declared',
            RequestEventType::RepresentativeVerified => 'Authorized representative verified',
            RequestEventType::RepresentativeRejected => 'Authorized representative not accepted',
            RequestEventType::Released => 'Released',
            RequestEventType::Cancelled => 'Request cancelled',
        };
    }

    /**
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<RequestStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(RequestStep::class, 'request_step_id');
    }

    /**
     * @return BelongsTo<OfficeVisit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(OfficeVisit::class, 'office_visit_id');
    }
}
