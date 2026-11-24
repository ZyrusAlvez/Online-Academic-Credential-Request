<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use Illuminate\Database\Eloquent\Builder;

/**
 * The registrar's Records filters (REQ-16-07).
 */
final class RegistrarRequestFilter
{
    public const OPEN = 'open';

    public const PROCESSING = 'processing';

    /** The request's due date has passed: "Request overdue" (REQ-30-10). */
    public const OVERDUE = 'overdue';

    /** The paper is past its office's deadline (REQ-30-10). */
    public const OFFICE_OVERDUE = 'office_overdue';

    /** Records' "Needs the Registrar" and "Waiting for the requester" filters (REQ-18-08). */
    public const NEEDS_REGISTRAR = 'needs_registrar';

    public const WAITING_FOR_REQUESTER = 'waiting_for_requester';

    /**
     * @param  string  $status  'processing', 'open', 'overdue', 'office_overdue', 'needs_registrar', 'waiting_for_requester', '' (all) or a RequestStatus value
     */
    public function __construct(
        public string $search = '',
        public string $status = '',
        public string $type = '',
        public string $office = '',
        /** The user who evaluated it (REQ-16-30). */
        public string $evaluator = '',
    ) {}

    /**
     * @return Builder<CredentialRequest>
     */
    public function query(): Builder
    {
        return self::matching(CredentialRequest::query(), $this->search)
            ->when($this->status === self::PROCESSING, fn (Builder $q) => $q->whereIn('status', RequestStatus::processing()))
            ->when($this->status === self::OPEN, fn (Builder $q) => $q->whereIn('status', RequestStatus::open()))
            ->when($this->status === self::OVERDUE, fn (Builder $q) => $q->overdue())
            ->when($this->status === self::OFFICE_OVERDUE, fn (Builder $q) => self::officeOverdue($q))
            ->when($this->status === self::NEEDS_REGISTRAR, fn (Builder $q) => self::needsRegistrar($q))
            ->when($this->status === self::WAITING_FOR_REQUESTER, fn (Builder $q) => $q->whereIn('status', self::waitingForRequester()))
            ->when(RequestStatus::tryFrom($this->status), fn (Builder $q, RequestStatus $status) => $q->where('status', $status))
            ->when($this->type !== '', fn (Builder $q) => $q->where('credential_type_id', (int) $this->type))
            ->when($this->office !== '', fn (Builder $q) => self::heldBy($q, (int) $this->office))
            ->when($this->evaluator !== '', fn (Builder $q) => $q->where('evaluated_by', (int) $this->evaluator))
            ->when(
                self::sortsByDueDate($this->status),
                fn (Builder $q) => $q->orderBy('effective_due_at'),   // most overdue first
                fn (Builder $q) => $q->latest('id'),
            );
    }

    /** The overdue filters list the earliest due date first (REQ-30-10). */
    public static function sortsByDueDate(string $status): bool
    {
        return in_array($status, [self::OVERDUE, self::OFFICE_OVERDUE], true);
    }

    /**
     * Requests being processed whose paper is past its office's deadline (REQ-30-10).
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public static function officeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', RequestStatus::processing())
            ->whereHas('visits', fn (Builder $q) => OfficeVisit::whereOverdue($q));
    }

    /**
     * Requests whose tracking number, student number or requester name matches the search; Records
     * and the stage queues share it (REQ-16-07, 25). A blank search matches everything.
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public static function matching(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('reference_no', 'like', "%{$term}%")
            ->orWhere('student_number', 'like', "%{$term}%")
            ->orWhere('requester_name', 'like', "%{$term}%")   // the request's copy (REQ-15-08)
            ->orWhereHas('student', fn (Builder $q) => $q
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]))));
    }

    /**
     * Requests being processed whose document an office has now: in the office, or forwarded by
     * it and not yet received (REQ-07-07, REQ-17-18).
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public static function heldBy(Builder $query, int $departmentId): Builder
    {
        return $query
            ->whereIn('status', RequestStatus::processing())
            ->whereHas('visits', fn (Builder $q) => OfficeVisit::whereHeldBy($q, $departmentId));
    }

    /**
     * Requests waiting for a Registrar decision: the evaluation, or a walk-in payment to validate.
     * Online payments are the Cashier's (REQ-18-08, REQ-25-04).
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public static function needsRegistrar(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('status', [RequestStatus::Submitted, RequestStatus::UnderEvaluation])
            ->orWhere(fn (Builder $walkIn) => $walkIn
                ->where('status', RequestStatus::PaymentVerification)
                ->where('request_type', RequestType::WalkIn)));
    }

    /**
     * Requests waiting for the requester to comply or respond.
     *
     * @return list<RequestStatus>
     */
    public static function waitingForRequester(): array
    {
        return [...RequestStatus::waitingForRequester(), RequestStatus::ReturnedForCustomerAction];
    }
}
