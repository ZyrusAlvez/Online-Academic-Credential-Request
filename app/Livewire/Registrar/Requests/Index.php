<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Requests')]
class Index extends Component
{
    use WithPagination;

    public const FILTER_REVIEW = 'review';

    public const FILTER_OPEN = 'open';

    #[Url(except: '')]
    public string $search = '';

    /** 'review', 'open', '' (all) or a RequestStatus value. */
    #[Url(except: self::FILTER_REVIEW)]
    public string $status = self::FILTER_REVIEW;

    #[Url(except: '')]
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $requests = CredentialRequest::query()
            ->with(['student:id,first_name,middle_name,last_name', 'credentialType:id,name', 'currentStep.department:id,name'])
            ->when($this->status === self::FILTER_REVIEW, fn (Builder $q) => $q->whereIn('status', [RequestStatus::PaymentReview, RequestStatus::RegistrarReview]))
            ->when($this->status === self::FILTER_OPEN, fn (Builder $q) => $q->whereIn('status', RequestStatus::open()))
            ->when(RequestStatus::tryFrom($this->status), fn (Builder $q, RequestStatus $status) => $q->where('status', $status))
            ->when($this->type !== '', fn (Builder $q) => $q->where('credential_type_id', (int) $this->type))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $term = trim($this->search);
                $q->where(fn (Builder $q) => $q
                    ->where('reference_no', 'like', "%{$term}%")
                    ->orWhere('student_number', 'like', "%{$term}%")
                    ->orWhereHas('student', fn (Builder $q) => $q
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])));
            })
            ->when(
                $this->status === self::FILTER_REVIEW,
                fn (Builder $q) => $q->oldest('id'),   // first come, first served
                fn (Builder $q) => $q->latest('id'),
            )
            ->paginate(20);

        return view('livewire.registrar.requests.index', [
            'requests' => $requests,
            'statuses' => RequestStatus::cases(),
            'types' => CredentialType::orderBy('name')->get(['id', 'name']),
            'reviewCount' => CredentialRequest::whereIn('status', [RequestStatus::PaymentReview, RequestStatus::RegistrarReview])->count(),
        ]);
    }
}
