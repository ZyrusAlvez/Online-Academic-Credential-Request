<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\RequesterMode;
use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use Database\Factories\CredentialRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A requester's request for a credential. Status changes only via App\Services\RequestWorkflow.
 *
 * @property string $reference_no
 * @property int $user_id
 * @property int $college_id
 * @property int $copies
 * @property AcademicStatus $academic_status
 * @property RequesterMode $requester_mode
 * @property bool $requires_payment
 * @property ?string $fee
 * @property ?string $amount_due
 * @property RequestStatus $status
 * @property ?Carbon $submitted_at
 * @property ?Carbon $evaluation_started_at
 * @property ?Carbon $accepted_at
 * @property ?Carbon $due_at
 * @property ?Carbon $adjusted_due_at
 * @property ?Carbon $effective_due_at
 * @property ?Carbon $ready_at
 * @property ?Carbon $released_at
 * @property ?int $processing_working_days
 */
class CredentialRequest extends Model
{
    /** @use HasFactory<CredentialRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'credential_type_id',
        'copies',
        'purpose',
        'receiving_institution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'academic_status' => AcademicStatus::class,
            'requester_mode' => RequesterMode::class,
            'requires_payment' => 'boolean',
            'fee' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'copies' => 'integer',
            'attended_from' => 'integer',
            'attended_to' => 'integer',
            'submitted_at' => 'datetime',
            'evaluation_started_at' => 'datetime',
            'requirements_completed_at' => 'datetime',
            'declined_at' => 'datetime',
            'accepted_at' => 'datetime',
            'due_at' => 'datetime',
            'adjusted_due_at' => 'datetime',
            'effective_due_at' => 'datetime',
            'ready_at' => 'datetime',
            'released_at' => 'datetime',
            'processing_working_days' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Being processed past its (adjusted) due date (REQ-05-12).
     *
     * @param  Builder<CredentialRequest>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::processing())->where('effective_due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, RequestStatus::processing(), true)
            && $this->effective_due_at?->isPast() === true;
    }

    public function getRouteKeyName(): string
    {
        return 'reference_no';
    }

    /**
     * The document owner.
     *
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
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * @return HasMany<RequestDeficiency, $this>
     */
    public function deficiencies(): HasMany
    {
        return $this->hasMany(RequestDeficiency::class)->orderBy('id');
    }

    /**
     * @return HasMany<RequestStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RequestStep::class)->orderBy('position');
    }

    /**
     * @return HasMany<OfficeVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(OfficeVisit::class)->orderBy('id');
    }

    /**
     * The visit of the office that has (or is being sent) the document now, if any.
     *
     * @return HasOne<OfficeVisit, $this>
     */
    public function openVisit(): HasOne
    {
        return $this->hasOne(OfficeVisit::class)
            ->whereIn('status', [VisitStatus::InTransit, VisitStatus::Received])
            ->latestOfMany();
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('id');
    }

    /**
     * @return HasMany<DelayNote, $this>
     */
    public function delayNotes(): HasMany
    {
        return $this->hasMany(DelayNote::class)->latest('id');
    }

    /**
     * @return HasMany<DueDatePause, $this>
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(DueDatePause::class)->orderBy('id');
    }

    /**
     * @return HasMany<RequestRepresentative, $this>
     */
    public function representatives(): HasMany
    {
        return $this->hasMany(RequestRepresentative::class)->orderBy('id');
    }

    /**
     * @return HasMany<RequestDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class)->orderBy('id');
    }

    /**
     * @return HasOne<Release, $this>
     */
    public function release(): HasOne
    {
        return $this->hasOne(Release::class);
    }

    /**
     * @return HasMany<RequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderBy('id');
    }

    /**
     * Where the request is, in words a requester understands (office names only).
     * Expects `openVisit.department` to be loaded for requests being processed.
     */
    public function whereabouts(): string
    {
        $office = $this->openVisit?->department->name;

        return match ($this->status) {
            RequestStatus::Submitted => 'Office of the Registrar – received for evaluation',
            RequestStatus::UnderEvaluation => 'Office of the Registrar – evaluating records and requirements',
            RequestStatus::PendingCompliance => 'Waiting for you to complete the requirements',
            RequestStatus::ReturnedForCustomerAction => 'Waiting for your response',
            RequestStatus::AwaitingPayment => 'Waiting for your payment',
            RequestStatus::PaymentVerification => 'Office of the Registrar – verifying payment',
            RequestStatus::AcceptedForProcessing, RequestStatus::InProgress, RequestStatus::ReturnedForCorrection => $office ?? 'Being processed',
            RequestStatus::ForFinalApproval => 'Office of the Registrar – final quality check',
            RequestStatus::ReadyForRelease => 'Office of the Registrar – ready for release',
            RequestStatus::Released => 'Released',
            RequestStatus::Declined => 'Declined',
            RequestStatus::Cancelled => 'Cancelled',
        };
    }
}
