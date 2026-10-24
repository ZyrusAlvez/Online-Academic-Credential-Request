<?php

namespace App\Livewire\Registrar\Identities;

use App\Actions\Identity\ReviewIdentityDocument;
use App\Enums\IdentityStatus;
use App\Enums\Role;
use App\Livewire\Concerns\TrimsInput;
use App\Models\IdentityDocument;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The Registrar's review of requesters' valid IDs (REQ-01-19).
 */
#[Title('Identity Checks')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    #[Url(except: '')]
    public string $search = '';

    #[Locked]
    public ?int $rejectingId = null;

    public string $remarks = '';

    public function mount(): void
    {
        abort_unless($this->registrar()->hasRole(Role::Registrar), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function verify(int $documentId, ReviewIdentityDocument $review): void
    {
        $document = $this->document($documentId);
        $review->handle($document, $this->registrar(), verified: true);

        $this->dispatch('notify', message: "Identity of {$document->user->name} verified.");
    }

    public function confirmReject(int $documentId): void
    {
        $this->document($documentId);

        $this->rejectingId = $documentId;
        $this->reset('remarks');
        $this->resetValidation();
        Flux::modal('reject-id')->show();
    }

    public function reject(ReviewIdentityDocument $review): void
    {
        $this->validate(['remarks' => ['required', 'string', 'max:500']], attributes: ['remarks' => 'reason']);

        $document = $this->document((int) $this->rejectingId);
        $review->handle($document, $this->registrar(), verified: false, remarks: $this->remarks);

        Flux::modal('reject-id')->close();
        $this->reset('rejectingId', 'remarks');
        $this->dispatch('notify', message: "ID of {$document->user->name} rejected; they have been notified.");
    }

    public function render(): View
    {
        $status = IdentityStatus::tryFrom($this->status);

        $requesters = User::query()
            ->where('role', Role::Student)
            ->when($status, fn ($query, IdentityStatus $status) => $query->where('identity_status', $status))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('email', 'like', "%{$this->search}%")
                ->orWhere('student_number', 'like', "%{$this->search}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$this->search}%"])))
            ->with(['latestIdentityDocument', 'college'])
            // Oldest upload first, so nobody waits longer than necessary.
            ->orderBy(IdentityDocument::select('created_at')->whereColumn('user_id', 'users.id')->latest('id')->limit(1))
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.registrar.identities.index', [
            'requesters' => $requesters,
            'statuses' => IdentityStatus::cases(),
        ]);
    }

    private function document(int $id): IdentityDocument
    {
        $document = IdentityDocument::with('user')->findOrFail($id);
        $this->authorize('review', $document);

        return $document;
    }

    private function registrar(): User
    {
        /** @var User */
        return Auth::user();
    }
}
