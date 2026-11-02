<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\RequestStatus;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Queries\RegistrarRequestFilter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Requests')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** 'review', 'processing', 'open', 'overdue', '' (all) or a RequestStatus value. */
    #[Url(except: RegistrarRequestFilter::REVIEW)]
    public string $status = RegistrarRequestFilter::REVIEW;

    #[Url(except: '')]
    public string $type = '';

    /** Department currently holding the request. */
    #[Url(except: '')]
    public string $office = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'office'], true)) {
            $this->resetPage();
        }
    }

    private function filter(): RegistrarRequestFilter
    {
        return new RegistrarRequestFilter($this->search, $this->status, $this->type, $this->office);
    }

    public function render(): View
    {
        $requests = $this->filter()->query()
            ->with(['student:id,first_name,middle_name,last_name', 'credentialType:id,name', 'openVisit.department:id,name'])
            ->paginate(20);

        return view('livewire.registrar.requests.index', [
            'requests' => $requests,
            'statuses' => RequestStatus::cases(),
            'types' => CredentialType::orderBy('name')->get(['id', 'name']),
            'offices' => Department::orderBy('name')->get(['id', 'name']),
            'reviewCount' => CredentialRequest::whereIn('status', RegistrarRequestFilter::needsRegistrar())->count(),
            'overdueCount' => CredentialRequest::overdue()->count(),
            'exportUrl' => route('registrar.requests.export', $this->filter()->toArray()),
        ]);
    }
}
