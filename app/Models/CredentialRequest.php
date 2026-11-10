<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\DocumentKind;
use App\Enums\RequesterMode;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\VisitStatus;
use Database\Factories\CredentialRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string $requester_name
 * @property ?string $contact_number
 * @property ?int $identity_document_id
 * @property ReviewStatus $identity_status
 * @property ?Carbon $identity_reviewed_at
 * @property ?string $identity_remarks
 * @property int $college_id
 * @property AcademicStatus $academic_status
 * @property RequesterMode $requester_mode
 * @property bool $requires_payment
 * @property ?string $fee
 * @property ?string $amount_due
 * @property RequestStatus $status
 * @property ?Carbon $submitted_at
 * @property ?Carbon $evaluation_started_at
 * @property ?int $evaluated_by
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
            'identity_status' => ReviewStatus::class,
            'identity_reviewed_at' => 'datetime',
            'requires_payment' => 'boolean',
            'fee' => 'decimal:2',
            'amount_due' => 'decimal:2',
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
     * The valid ID sent with this request, checked on it (REQ-15-09).
     *
     * @return BelongsTo<IdentityDocument, $this>
     */
    public function identityDocument(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function identityReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_reviewed_by');
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
     * While under evaluation the request belongs to its evaluator; other registrars are kept out
     * (REQ-16-09).
     */
    public function isEvaluatedBySomeoneElse(User $user): bool
    {
        return $this->status === RequestStatus::UnderEvaluation
            && $this->evaluated_by !== null
            && $this->evaluated_by !== $user->id;
    }

    /**
     * The checklist results of a stage, keyed by item value (REQ-16-22). Uses the loaded `checks`.
     *
     * @return array<string, CheckResult>
     */
    public function checkResults(CheckStage $stage): array
    {
        return $this->checks
            ->filter(fn (RequestCheck $check): bool => $check->item->stage() === $stage)
            ->mapWithKeys(fn (RequestCheck $check): array => [$check->item->value => $check->result])
            ->all();
    }

    /**
     * @return HasMany<RequestCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(RequestCheck::class)->orderBy('id');
    }

    /**
     * What Require Office Visit found missing, each resolved when the registrar marks it received
     * (REQ-20-04, 09).
     *
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
            ->whereIn('status', VisitStatus::open())
            ->latestOfMany();
    }

    /**
     * The office the paper goes to after the one holding it now (REQ-17-11): for a forwarded
     * paper not yet received, the office it was forwarded to (REQ-17-18). Reads the loaded
     * `openVisit.department`, `openVisit.step` and `steps.department`.
     */
    public function nextOffice(): ?Department
    {
        $visit = $this->openVisit;

        if ($visit?->status === VisitStatus::InTransit) {
            return $visit->department;
        }

        // A copy, so the loaded visit does not point back at this request.
        return $visit !== null ? (clone $visit)->setRelation('credentialRequest', $this)->nextStep()?->department : null;
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
     * The requester's copies of the credential's documentary requirements (REQ-04-19).
     *
     * @return HasMany<RequestDocument, $this>
     */
    public function requirementDocuments(): HasMany
    {
        return $this->documents()->where('kind', DocumentKind::Requirement);
    }

    /**
     * Each documentary requirement with its files: the credential's current list, then any
     * requirement a file was sent for that the list no longer has. Expects `credentialType` and
     * `requirementDocuments` to be loaded.
     *
     * @return array<string, Collection<int, RequestDocument>>
     */
    public function requirementFiles(): array
    {
        $documents = $this->requirementDocuments;
        $sent = $documents->pluck('requirement')->filter()->map(fn ($requirement): string => (string) $requirement)->all();
        $files = [];

        foreach (array_unique([...($this->credentialType->requirements ?? []), ...$sent]) as $requirement) {
            $files[$requirement] = $documents->where('requirement', $requirement)->values();
        }

        return $files;
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
     * Where the request is, in words a requester understands (office names only): the office
     * holding the paper, the sender of one not yet received (REQ-17-18). Expects
     * `openVisit.department` and `openVisit.fromDepartment` to be loaded for requests being
     * processed.
     */
    public function whereabouts(): string
    {
        $office = $this->openVisit?->holder()->name;

        return match ($this->status) {
            RequestStatus::Submitted => 'Office of the Registrar – received for evaluation',
            RequestStatus::UnderEvaluation => 'Office of the Registrar – evaluating records and requirements',
            RequestStatus::ReturnedForClarification => 'Waiting for you to correct your request',
            RequestStatus::PendingCompliance => 'Waiting for you at the Office of the Registrar',
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

    /**
     * whereabouts() for staff (Records, the record page, the Admin dashboard): it names the
     * requester instead of addressing them (REQ-16-23).
     */
    public function staffWhereabouts(): string
    {
        return match ($this->status) {
            RequestStatus::ReturnedForClarification => 'Waiting for the requester to correct the request',
            RequestStatus::PendingCompliance => 'Waiting for the requester at the Office of the Registrar',
            RequestStatus::ReturnedForCustomerAction => "Waiting for the requester's response",
            RequestStatus::AwaitingPayment => "Waiting for the requester's payment",
            default => $this->whereabouts(),
        };
    }
}
