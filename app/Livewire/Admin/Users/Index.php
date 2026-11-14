<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Staff\SetAccountStatus;
use App\Enums\Role;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\RoleForm;
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

    public RoleForm $roleForm;

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

    public function editRole(User $account): void
    {
        $this->authorize('changeRole', $account);

        $this->resetValidation();
        $this->roleForm->edit($account);
        Flux::modal('change-role')->show();
    }

    public function changeRole(): void
    {
        /** @var User|null $account */
        $account = $this->roleForm->account;
        abort_if($account === null, 404);
        $this->authorize('changeRole', $account);

        if (! $this->roleForm->save($this->actor())) {
            return;
        }

        Flux::modal('change-role')->close();
        $this->dispatch('notify', message: "{$account->name} is now {$account->role->label()}.");
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
        $term = trim($this->search);

        $accounts = User::query()
            ->with('department:id,name,code')
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                ->orWhere('email', 'like', "%{$term}%")))
            ->when(Role::tryFrom($this->role), fn ($query, $role) => $query->where('role', $role))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return view('livewire.admin.users.index', [
            'accounts' => $accounts,
            'roles' => Role::cases(),
            'departments' => Department::active()->orderBy('type')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
