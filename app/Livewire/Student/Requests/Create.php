<?php

namespace App\Livewire\Student\Requests;

use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\CredentialRequestForm;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Request a credential (spec 04); optionally declare a representative (spec 11).
 */
#[Title('New Request')]
class Create extends Component
{
    use TrimsInput;
    use WithFileUploads;

    public CredentialRequestForm $form;

    /** @var UploadedFile|null */
    public $spa = null;

    /** @var UploadedFile|null */
    public $representativeId = null;

    public function mount(): void
    {
        $this->authorize('create', CredentialRequest::class);
    }

    public function submit(RequestWorkflow $workflow): void
    {
        $this->authorize('create', CredentialRequest::class);

        $data = $this->form->validatedRequest();
        $representative = $this->form->representative();
        $this->validate([
            'spa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'representativeId' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], attributes: ['spa' => 'SPA', 'representativeId' => "representative's ID"]);

        try {
            $request = $workflow->submit($this->owner(), $data);

            if ($representative !== null) {
                $workflow->declareRepresentative($request, $this->owner(), $representative, $this->spa, $this->representativeId);
            }
        } catch (InvalidTransition $e) {
            $this->addError('form.credential_type_id', $e->getMessage());

            return;
        }

        session()->flash('notify', "Request {$request->reference_no} received for evaluation.");
        $this->redirectRoute('student.requests.show', $request, navigate: true);
    }

    public function render(): View
    {
        $types = CredentialType::requestable()->with('steps.department')->orderBy('name')->get();

        return view('livewire.student.requests.create', [
            'types' => $types,
            'selectedType' => $types->firstWhere('id', (int) $this->form->credential_type_id),
            'owner' => $this->owner()->loadMissing('college'),
        ]);
    }

    private function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
