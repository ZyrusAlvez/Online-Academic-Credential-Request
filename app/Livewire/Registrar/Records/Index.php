<?php

namespace App\Livewire\Registrar\Records;

use App\Enums\RequestStatus;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Queries\DashboardScope;
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

    /** The user who evaluated the request (REQ-16-30). */
    #[Url(except: '')]
    public string $evaluator = '';

    /** The dashboard number this list was opened from, with its period and office (REQ-34-17). */
    #[Url(except: '')]
    public string $scope = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'office', 'evaluator'], true)) {
            $this->resetPage();
        }
    }

    /** The chip's ✕: back to every request (REQ-34-17). */
    public function clearScope(): void
    {
        $this->scope = $this->from = $this->to = $this->at = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $scope = new DashboardScope($this->scope, $this->from, $this->to, $this->at);

        // One row per application with a credential that matches the filters (REQ-27-08).
        $matching = (new RegistrarRequestFilter($this->search, $this->status, $this->type, $this->office, $this->evaluator))->query()
            ->when($scope->active(), fn ($q) => $scope->apply($q))
            ->reorder()->select('application_id');

        $requests = CredentialRequest::query()
            ->where('item_no', 1)
            ->whereIn('application_id', $matching)
            ->with([
                'application.credentialRequests.credentialType:id,name',
                'application.credentialRequests.openVisit.department:id,name',
                'application.credentialRequests.openVisit.fromDepartment:id,name',
                'evaluator:id,first_name,middle_name,last_name,email',
            ])
            ->when(RegistrarRequestFilter::sortsByDueDate($this->status), fn ($q) => $q->orderBy('effective_due_at'), fn ($q) => $q->latest('id'))
            ->paginate(20);

        return view('livewire.registrar.records.index', [
            'requests' => $requests,
            'scopeLabel' => $scope->active() ? $scope->label() : null,
            'statuses' => RequestStatus::cases(),
            'types' => CredentialType::active()->orderBy('name')->get(['id', 'name']),
            'offices' => Department::active()->orderBy('name')->get(['id', 'name']),
            // Everyone who has evaluated a request, former registrars too (REQ-16-30).
            'evaluators' => User::query()
                ->whereIn('id', CredentialRequest::query()->whereNotNull('evaluated_by')->select('evaluated_by'))
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'email']),
            'overdueCount' => CredentialRequest::overdue()->count(),
            'officeOverdueCount' => RegistrarRequestFilter::officeOverdue(CredentialRequest::query())->count(),
        ]);
    }
}
