<?php

namespace App\Livewire\Registrar\Users;

use App\Actions\Staff\SetAccountStatus;
use App\Enums\Role;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\StaffForm;
use App\Models\Department;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Accounts')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    public StaffForm $form;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('staff-form')->show();
    }

    public function edit(User $account): void
    {
        $this->authorize('update', $account);

        $this->resetValidation();
        $this->form->edit($account);
        Flux::modal('staff-form')->show();
    }

    public function save(): void
    {
        $this->form->account
            ? $this->authorize('update', $this->form->account)
            : $this->authorize('create', User::class);

        $account = $this->form->save($this->actor());

        Flux::modal('staff-form')->close();
        $this->dispatch('notify', message: "{$account->name}'s account saved.");
    }

    public function toggleStatus(User $account, SetAccountStatus $setStatus): void
    {
        $this->authorize('setStatus', $account);

        $setStatus->handle($this->actor(), $account, ! $account->is_active);

        $this->dispatch('notify', message: $account->name.($account->is_active ? ' activated.' : ' deactivated.'));
    }

    private function actor(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function render(): View
    {
        $accounts = User::query()
            ->with('department:id,name,code')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when(Role::tryFrom($this->role), fn ($query, $role) => $query->where('role', $role))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return view('livewire.registrar.users.index', [
            'accounts' => $accounts,
            'roles' => Role::cases(),
            'departments' => Department::active()->orderBy('type')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
