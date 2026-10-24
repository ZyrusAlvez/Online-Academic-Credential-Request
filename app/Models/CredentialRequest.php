<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Enums\StudentStatus;
use Database\Factories\CredentialRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A student's request for a credential. Status changes only via App\Services\RequestWorkflow.
 *
 * @property string $reference_no
 * @property RequestStatus $status
 * @property StudentStatus $student_status
 * @property bool $requires_payment
 * @property ?string $fee
 * @property int $copies
 * @property ?Carbon $routed_at
 * @property ?Carbon $due_at
 * @property ?Carbon $ready_at
 * @property ?Carbon $released_at
 * @property ?Carbon $cancelled_at
 * @property Carbon $created_at
 */
class CredentialRequest extends Model
{
    /** @use HasFactory<CredentialRequestFactory> */
    use HasFactory;

    /**
     * Only descriptive fields are fillable; status and lifecycle timestamps are set by the workflow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'credential_type_id',
        'college_id',
        'student_number',
        'program',
        'student_status',
        'last_school_year',
        'purpose',
        'copies',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'student_status' => StudentStatus::class,
            'requires_payment' => 'boolean',
            'fee' => 'decimal:2',
            'copies' => 'integer',
            'routed_at' => 'datetime',
            'due_at' => 'datetime',
            'ready_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * In progress past the target turnaround.
     *
     * @param  Builder<CredentialRequest>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', RequestStatus::InProgress)->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === RequestStatus::InProgress && $this->due_at?->isPast() === true;
    }

    public function getRouteKeyName(): string
    {
        return 'reference_no';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<CredentialType, $this>
     */
    public function credentialType(): BelongsTo
    {
        return $this->belongsTo(CredentialType::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'college_id');
    }

    /**
     * @return HasMany<RequestStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RequestStep::class)->orderBy('position');
    }

    /**
     * The step that currently holds the document (pending or on hold), if any.
     *
     * @return HasOne<RequestStep, $this>
     */
    public function currentStep(): HasOne
    {
        return $this->hasOne(RequestStep::class)
            ->whereIn('status', [StepStatus::Pending, StepStatus::OnHold]);
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('id');
    }

    /**
     * @return HasMany<RequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderBy('id');
    }

    /**
     * Where the physical document is right now, in words a student understands.
     * Expects `currentStep.department` to be loaded for in-progress requests.
     */
    public function whereabouts(): string
    {
        return match ($this->status) {
            RequestStatus::AwaitingPayment => 'Waiting for your payment',
            RequestStatus::PaymentReview => 'Office of the Registrar – verifying payment',
            RequestStatus::RegistrarReview => 'Office of the Registrar – reviewing request',
            RequestStatus::InProgress => $this->currentStep?->department->name ?? 'Being routed',
            RequestStatus::ReadyForRelease => 'Office of the Registrar – ready for pickup',
            RequestStatus::Released => 'Released',
            RequestStatus::Cancelled => 'Cancelled',
        };
    }
}
