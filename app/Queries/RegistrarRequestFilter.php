<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * The registrar's Records filters (REQ-16-07).
 */
final class RegistrarRequestFilter
{
    public const OPEN = 'open';

    public const PROCESSING = 'processing';

    public const OVERDUE = 'overdue';

    /**
     * @param  string  $status  'processing', 'open', 'overdue', '' (all) or a RequestStatus value
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
     * Requests being processed whose document an office has now, forwarded or received
     * (REQ-07-07).
     *
     * @param  Builder<CredentialRequest>  $query
     * @return Builder<CredentialRequest>
     */
    public static function heldBy(Builder $query, int $departmentId): Builder
    {
        return $query
            ->whereIn('status', RequestStatus::processing())
            ->whereHas('visits', fn (Builder $q) => $q
                ->where('department_id', $departmentId)
                ->whereIn('status', VisitStatus::open()));
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
}
