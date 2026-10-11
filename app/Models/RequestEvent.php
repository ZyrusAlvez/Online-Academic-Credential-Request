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
     * One-line description used by the timeline, notifications and emails.
     * Expects `department` to be loaded for step events.
     */
    public function summary(): string
    {
        $office = $this->department->name ?? 'an office';

        return match ($this->type) {
            RequestEventType::Submitted => 'Request submitted',
            RequestEventType::PaymentSubmitted => 'Proof of payment uploaded',
            RequestEventType::PaymentRejected => 'Payment not accepted – please upload a new receipt',
            RequestEventType::PaymentAccepted => 'Payment verified by the Registrar',
            RequestEventType::CollegeChanged => 'College corrected by the Registrar',
            RequestEventType::Routed => 'Approved and sent to the signing offices',
            RequestEventType::StepSigned => "Signed by {$office}",
            RequestEventType::StepHeld => "On hold at {$office}",
            RequestEventType::StepResumed => "Hold lifted at {$office}",
            RequestEventType::StepSkipped => "{$office} step skipped by the Registrar",
            RequestEventType::ReadyForRelease => 'Ready for release at the Office of the Registrar',
            RequestEventType::Released => 'Released to the student',
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
}
