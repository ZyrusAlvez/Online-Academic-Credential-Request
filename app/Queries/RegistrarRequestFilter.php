<?php

namespace App\Queries;

use App\Enums\RequestStatus;
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

    public const OVERDUE = 'overdue';

    /** The dashboard's "Needs the Registrar" and "Pending compliance" tiles (REQ-18-08). */
    public const NEEDS_REGISTRAR = 'needs_registrar';

    public const WAITING_FOR_REQUESTER = 'waiting_for_requester';

    /**
     * @param  string  $status  'processing', 'open', 'overdue', 'needs_registrar', 'waiting_for_requester', '' (all) or a RequestStatus value
     */
    public function __construct(
        public string $search = '',
        public string $status = '',
        public string $type = '',
        public string $office = '',
    ) {}

    /**
     * @return Builder<CredentialRequest>
     */
    public function query(): Builder
    {
        $term = trim($this->search);

        return CredentialRequest::query()
            ->when($this->status === self::PROCESSING, fn (Builder $q) => $q->whereIn('status', RequestStatus::processing()))
            ->when($this->status === self::OPEN, fn (Builder $q) => $q->whereIn('status', RequestStatus::open()))
            ->when($this->status === self::OVERDUE, fn (Builder $q) => $q->overdue())
            ->when($this->status === self::NEEDS_REGISTRAR, fn (Builder $q) => $q->whereIn('status', self::needsRegistrar()))
            ->when($this->status === self::WAITING_FOR_REQUESTER, fn (Builder $q) => $q->whereIn('status', self::waitingForRequester()))
            ->when(RequestStatus::tryFrom($this->status), fn (Builder $q, RequestStatus $status) => $q->where('status', $status))
            ->when($this->type !== '', fn (Builder $q) => $q->where('credential_type_id', (int) $this->type))
            ->when($this->office !== '', fn (Builder $q) => self::heldBy($q, (int) $this->office))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference_no', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%")
                ->orWhere('requester_name', 'like', "%{$term}%")   // the request's copy (REQ-15-08)
                ->orWhereHas('student', fn (Builder $q) => $q
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]))))
            ->when(
                $this->status === self::OVERDUE,
                fn (Builder $q) => $q->orderBy('effective_due_at'),   // most overdue first
                fn (Builder $q) => $q->latest('id'),
            );
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
     * Requests waiting for a Registrar decision: evaluation or payment validation.
     *
     * @return list<RequestStatus>
     */
    public static function needsRegistrar(): array
    {
        return [RequestStatus::Submitted, RequestStatus::UnderEvaluation, RequestStatus::PaymentVerification];
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
