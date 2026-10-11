<?php

namespace App\Livewire\Student\Requests;

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
    public CredentialRequestForm $form;

    public function mount(): void
    {
        $this->authorize('create', CredentialRequest::class);
    }

    public function submit(RequestWorkflow $workflow): void
    {
        $this->authorize('create', CredentialRequest::class);

        /** @var User $student */
        $student = Auth::user();
        $request = $workflow->submit($student, $this->form->validated());

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
}
