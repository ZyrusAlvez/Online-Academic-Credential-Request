<?php

namespace App\Livewire\Student;

use App\Models\CredentialRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Request Tracker')]
class Dashboard extends Component
{
    use WithPagination;

    /** Narrows the list by credential name or tracking number (REQ-09-20). */
    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);

        // One row per application, its first credential, matched by any of its credentials (REQ-27-06).
        $requests = CredentialRequest::query()
            ->whereBelongsTo(Auth::user(), 'student')
            ->where('item_no', 1)
            ->when($term !== '', fn (Builder $query) => $query->whereIn('application_id', CredentialRequest::query()
                ->select('application_id')
                ->where('reference_no', 'like', "%{$term}%")
                ->orWhereHas('credentialType', fn (Builder $type) => $type->where('name', 'like', "%{$term}%"))))
            ->with([
                'application.credentialRequests.credentialType:id,name',
                'application.credentialRequests.openVisit.department:id,name',
                'application.credentialRequests.openVisit.fromDepartment:id,name',
            ])
            ->latest('id')
            ->paginate(10);

        return view('livewire.student.dashboard', [
            'requests' => $requests,
            'searching' => $term !== '',
        ]);
    }
}
