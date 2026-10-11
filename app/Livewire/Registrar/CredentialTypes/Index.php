<?php

namespace App\Livewire\Registrar\CredentialTypes;

use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\CredentialTypeForm;
use App\Models\CredentialType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Credential Types')]
class Index extends Component
{
    use TrimsInput;

    public CredentialTypeForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialType::class);
    }

    public function create(): void
    {
        $this->authorize('create', CredentialType::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('credential-type-form')->show();
    }

    public function edit(CredentialType $credentialType): void
    {
        $this->authorize('update', $credentialType);

        $this->resetValidation();
        $this->form->edit($credentialType);
        Flux::modal('credential-type-form')->show();
    }

    public function save(): void
    {
        $this->form->credentialType
            ? $this->authorize('update', $this->form->credentialType)
            : $this->authorize('create', CredentialType::class);

        $isNew = $this->form->credentialType === null;
        $credentialType = $this->form->save();

        Flux::modal('credential-type-form')->close();

        if ($isNew) {
            session()->flash('notify', "{$credentialType->name} created. Now add its workflow steps.");
            $this->redirectRoute('registrar.credential-types.workflow', $credentialType, navigate: true);

            return;
        }

        $this->dispatch('notify', message: "{$credentialType->name} saved.");
    }

    public function toggleStatus(CredentialType $credentialType): void
    {
        $this->authorize('update', $credentialType);

        $credentialType->update(['is_active' => ! $credentialType->is_active]);

        $this->dispatch('notify', message: $credentialType->name.($credentialType->is_active ? ' activated.' : ' deactivated.'));
    }

    public function render(): View
    {
        return view('livewire.registrar.credential-types.index', [
            'credentialTypes' => CredentialType::withCount('steps')->orderBy('name')->get(),
        ]);
    }
}
