<?php

namespace App\Livewire\Student\Requests;

use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\CredentialRequestForm;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New Request')]
class Create extends Component
{
    use TrimsInput;

    public CredentialRequestForm $form;

    public function mount(): void
    {
        $this->authorize('create', CredentialRequest::class);

        $this->form->fillFromProfile($this->student());
    }

    public function submit(RequestWorkflow $workflow): void
    {
        $this->authorize('create', CredentialRequest::class);

        $request = $workflow->submit($this->student(), $this->form->validated());

        session()->flash('notify', "Request {$request->reference_no} submitted.");
        $this->redirectRoute('student.requests.show', $request, navigate: true);
    }

    public function render(): View
    {
        $types = CredentialType::requestable()->orderBy('name')->get();

        return view('livewire.student.requests.create', [
            'types' => $types,
            'selectedType' => $types->firstWhere('id', $this->form->credential_type_id),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function student(): User
    {
        /** @var User */
        return Auth::user();
    }
}
