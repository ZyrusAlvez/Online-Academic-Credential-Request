<?php

namespace App\Livewire\Registrar\Purposes;

use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\PurposeForm;
use App\Models\Purpose;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The purposes offered to requesters on New Request (spec 13).
 */
#[Title('Purposes')]
class Index extends Component
{
    use TrimsInput;
    use WithPagination;

    public PurposeForm $form;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Purpose::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', Purpose::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('purpose-form')->show();
    }

    public function edit(Purpose $purpose): void
    {
        $this->authorize('update', $purpose);

        $this->resetValidation();
        $this->form->edit($purpose);
        Flux::modal('purpose-form')->show();
    }

    public function save(): void
    {
        $this->form->purpose
            ? $this->authorize('update', $this->form->purpose)
            : $this->authorize('create', Purpose::class);

        $purpose = $this->form->save();

        Flux::modal('purpose-form')->close();
        $this->dispatch('notify', message: "{$purpose->name} saved.");
    }

    public function toggleStatus(Purpose $purpose): void
    {
        $this->authorize('update', $purpose);

        $purpose->update(['is_active' => ! $purpose->is_active]);

        $this->dispatch('notify', message: $purpose->name.($purpose->is_active ? ' activated.' : ' deactivated.'));
    }

    public function render(): View
    {
        $term = trim($this->search);

        $purposes = Purpose::query()
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.registrar.purposes.index', ['purposes' => $purposes]);
    }
}
