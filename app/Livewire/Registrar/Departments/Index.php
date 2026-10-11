<?php

namespace App\Livewire\Registrar\Departments;

use App\Enums\DepartmentType;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\DepartmentForm;
use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Departments')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    public DepartmentForm $form;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Department::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', Department::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('department-form')->show();
    }

    public function edit(Department $department): void
    {
        $this->authorize('update', $department);

        $this->resetValidation();
        $this->form->edit($department);
        Flux::modal('department-form')->show();
    }

    public function save(): void
    {
        $this->form->department
            ? $this->authorize('update', $this->form->department)
            : $this->authorize('create', Department::class);

        $department = $this->form->save();

        Flux::modal('department-form')->close();
        $this->dispatch('notify', message: "{$department->name} saved.");
    }

    public function toggleStatus(Department $department): void
    {
        $this->authorize('update', $department);

        $department->update(['is_active' => ! $department->is_active]);

        $this->dispatch('notify', message: $department->name.($department->is_active ? ' activated.' : ' deactivated.'));
    }

    public function render(): View
    {
        $departments = Department::query()
            ->withCount('staff')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->when(DepartmentType::tryFrom($this->type), fn ($query, $type) => $query->where('type', $type))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.registrar.departments.index', [
            'departments' => $departments,
            'types' => DepartmentType::cases(),
        ]);
    }
}
