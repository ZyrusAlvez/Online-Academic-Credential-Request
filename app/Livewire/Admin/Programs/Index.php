<?php

namespace App\Livewire\Admin\Programs;

use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\ProgramForm;
use App\Models\Department;
use App\Models\Program;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The programs and strands offered to requesters on New Request and Settings → Profile (spec 12).
 */
#[Title('Programs')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    public ProgramForm $form;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $college = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Program::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'college', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', Program::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('program-form')->show();
    }

    public function edit(Program $program): void
    {
        $this->authorize('update', $program);

        $this->resetValidation();
        $this->form->edit($program);
        Flux::modal('program-form')->show();
    }

    public function save(): void
    {
        $this->form->program
            ? $this->authorize('update', $this->form->program)
            : $this->authorize('create', Program::class);

        $program = $this->form->save();

        Flux::modal('program-form')->close();
        $this->dispatch('notify', message: "{$program->name} saved.");
    }

    public function toggleStatus(Program $program): void
    {
        $this->authorize('update', $program);

        $program->update(['is_active' => ! $program->is_active]);

        $this->dispatch('notify', message: $program->name.($program->is_active ? ' activated.' : ' deactivated.'));
    }

    public function render(): View
    {
        $term = trim($this->search);

        $programs = Program::query()
            ->with('college:id,name,code')
            ->when($term !== '', fn ($query) => $query->where('programs.name', 'like', "%{$term}%"))
            ->when($this->college !== '', fn ($query) => $query->where('programs.department_id', (int) $this->college))
            ->when($this->status !== '', fn ($query) => $query->where('programs.is_active', $this->status === 'active'))
            ->join('departments', 'departments.id', '=', 'programs.department_id')
            ->orderBy('departments.name')
            ->orderBy('programs.name')
            ->select('programs.*')
            ->paginate(20);

        return view('livewire.admin.programs.index', [
            'programs' => $programs,
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
