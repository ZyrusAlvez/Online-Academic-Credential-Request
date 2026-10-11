<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Models\CredentialRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * The registrar's request-list filters, shared by the Livewire list and the CSV export.
 */
final class RegistrarRequestFilter
{
    public const REVIEW = 'review';

    public const OPEN = 'open';

    public const OVERDUE = 'overdue';

    /**
     * @param  string  $status  'review', 'open', 'overdue', '' (all) or a RequestStatus value
     */
    public function __construct(
        public string $search = '',
        public string $status = self::REVIEW,
        public string $type = '',
        public string $office = '',
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            search: (string) ($input['search'] ?? ''),
            status: (string) ($input['status'] ?? self::REVIEW),
            type: (string) ($input['type'] ?? ''),
            office: (string) ($input['office'] ?? ''),
        );
    }

    /**
     * @return Builder<CredentialRequest>
     */
    public function query(): Builder
    {
        $term = trim($this->search);

        return CredentialRequest::query()
            ->when($this->status === self::REVIEW, fn (Builder $q) => $q->whereIn('status', [RequestStatus::PaymentReview, RequestStatus::RegistrarReview]))
            ->when($this->status === self::OPEN, fn (Builder $q) => $q->whereIn('status', RequestStatus::open()))
            ->when($this->status === self::OVERDUE, fn (Builder $q) => $q->overdue())
            ->when(RequestStatus::tryFrom($this->status), fn (Builder $q, RequestStatus $status) => $q->where('status', $status))
            ->when($this->type !== '', fn (Builder $q) => $q->where('credential_type_id', (int) $this->type))
            ->when($this->office !== '', fn (Builder $q) => $q
                ->where('status', RequestStatus::InProgress)
                ->whereHas('steps', fn (Builder $q) => $q
                    ->where('department_id', (int) $this->office)
                    ->whereIn('status', [StepStatus::Pending, StepStatus::OnHold])))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference_no', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%")
                ->orWhereHas('student', fn (Builder $q) => $q
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]))))
            ->when(
                in_array($this->status, [self::REVIEW, self::OVERDUE], true),
                fn (Builder $q) => $q->orderBy($this->status === self::OVERDUE ? 'due_at' : 'id'),   // oldest first
                fn (Builder $q) => $q->latest('id'),
            );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'type' => $this->type,
            'office' => $this->office,
        ], fn (string $value, string $key) => $value !== '' || $key === 'status', ARRAY_FILTER_USE_BOTH);
    }
}
