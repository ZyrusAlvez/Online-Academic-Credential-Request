<?php

namespace App\Livewire\Registrar\Records;

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

/**
 * Every request, for viewing and tracking only (REQ-16-07).
 */
#[Title('Records')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** 'processing', 'open', 'overdue', 'office_overdue', '' (all) or a RequestStatus value. */
    #[Url(except: '')]
    public string $status = '';

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

    public function render(): View
    {
        // One row per application with a credential that matches the filters (REQ-27-08).
        $matching = (new RegistrarRequestFilter($this->search, $this->status, $this->type, $this->office))->query()
            ->reorder()->select('application_id');

        $requests = CredentialRequest::query()
            ->where('item_no', 1)
            ->whereIn('application_id', $matching)
            ->with([
                'application.credentialRequests.credentialType:id,name',
                'application.credentialRequests.openVisit.department:id,name',
                'application.credentialRequests.openVisit.fromDepartment:id,name',
            ])
            ->when(RegistrarRequestFilter::sortsByDueDate($this->status), fn ($q) => $q->orderBy('effective_due_at'), fn ($q) => $q->latest('id'))
            ->paginate(20);

        return view('livewire.registrar.records.index', [
            'requests' => $requests,
            'statuses' => RequestStatus::cases(),
            'types' => CredentialType::active()->orderBy('name')->get(['id', 'name']),
            'offices' => Department::active()->orderBy('name')->get(['id', 'name']),
            'overdueCount' => CredentialRequest::overdue()->count(),
            'officeOverdueCount' => RegistrarRequestFilter::officeOverdue(CredentialRequest::query())->count(),
        ]);
    }
}
