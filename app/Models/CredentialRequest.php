<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RequesterMode;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\ReviewStatus;
use App\Enums\RouteTask;
use App\Enums\Sex;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use Carbon\CarbonInterface;
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
 * @property int $application_id
 * @property int $item_no 1 for the application's first credential (spec 27)
 * @property ?int $user_id null for a walk-in request not linked to an account yet (spec 26)
 * @property string $requester_name
 * @property ?Sex $sex null on requests made before spec 28
 * @property ?string $contact_number
 * @property ?string $requester_email a walk-in client's email, to link the request (REQ-26-07)
 * @property ?int $recorded_by the registrar who recorded a walk-in request
 * @property ?int $identity_document_id
 * @property ReviewStatus $identity_status
 * @property ?Carbon $identity_reviewed_at
 * @property ?string $identity_remarks
 * @property ?list<string> $terms the ticked Term values, when the credential asks for them (spec 29)
 * @property ?string $academic_year e.g. "2025-2026"
 * @property int $college_id
 * @property AcademicStatus $academic_status
 * @property RequesterMode $requester_mode
 * @property RequestType $request_type
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

    /** At most this many open online requests per requester account (REQ-33-01). */
    public const OPEN_LIMIT = 5;

    /** Requests are made online unless the Registrar records a walk-in (spec 25). */
    protected $attributes = [
        'request_type' => 'online',
        'item_no' => 1,
    ];

    public function isWalkIn(): bool
    {
        return $this->request_type === RequestType::WalkIn;
    }

    /**
     * The application this credential is part of (spec 27).
     *
     * @return BelongsTo<RequestApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RequestApplication::class, 'application_id');
    }

    /**
     * The application's other credentials.
     *
     * @return HasMany<CredentialRequest, $this>
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(self::class, 'application_id', 'application_id')
            ->whereKeyNot($this->getKey())->orderBy('item_no');
    }

    /**
     * The application's first credential, which carries it until acceptance (REQ-27-03).
     */
    public function isLead(): bool
    {
        return $this->item_no === 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'sex' => Sex::class,
            'academic_status' => AcademicStatus::class,
            'requester_mode' => RequesterMode::class,
            'request_type' => RequestType::class,
            'identity_status' => ReviewStatus::class,
            'identity_reviewed_at' => 'datetime',
            'requires_payment' => 'boolean',
            'terms' => 'array',
            'fee' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'item_no' => 'integer',
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
     * The tracking number shown to requesters, e.g. rc26-00042 (REQ-04-03). Requests numbered
     * earlier keep their number, cr26-… included.
     */
    public static function trackingNumber(int $id, CarbonInterface $createdAt): string
    {
        // "rc" (lowercase) + the two-digit year it was made, e.g. rc26 for 2026.
        $prefix = 'rc'.$createdAt->format('y');

        // The request's id, padded with zeros to 5 digits (00042). Past 99999 it simply grows to
        // 6 digits and more (100000), so the number never runs out or repeats.
        $number = str_pad((string) $id, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$number}";
    }

    /**
     * The document owner's account; none for a walk-in request not linked yet (spec 26).
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The registrar who recorded a walk-in request (REQ-26-04).
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Where to reach the requester: their account's email, else the walk-in client's (REQ-26-10).
     */
    public function requesterEmail(): ?string
    {
        return $this->student->email ?? $this->requester_email;
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
        // Every stored part is Initial Evaluation's, the only checklist (REQ-16-17, 19).
        $checks = match ($stage) {
            CheckStage::Evaluation => $this->checks,
        };

        return $checks->mapWithKeys(fn (RequestCheck $check): array => [$check->item->value => $check->result])->all();
    }

    /**
     * @return HasMany<RequestCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(RequestCheck::class)->orderBy('id');
    }

    /**
     * What Require Office Visit asked for, each resolved when the registrar marks it received
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
     * The document is with the Registrar for the final quality check, not a correction sent from
     * it (REQ-16-05, 19).
     */
    public function isAtFinalCheck(): bool
    {
        $visit = $this->loadMissing('openVisit.step')->openVisit;

        return $visit !== null && $visit->step->task === RouteTask::FinalCheck && $visit->purpose !== VisitPurpose::Correction;
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
     * The representative who may come for the owner: the latest declared on New Request and not
     * rejected. Null means the owner comes in person (REQ-11-05).
     */
    public function claimingRepresentative(): ?RequestRepresentative
    {
        return $this->representatives->reject(fn (RequestRepresentative $representative) => $representative->status === ReviewStatus::Rejected)->last();
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
