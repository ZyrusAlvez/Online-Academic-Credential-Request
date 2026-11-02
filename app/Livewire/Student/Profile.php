<?php

namespace App\Livewire\Student;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\IdentityStatus;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The requester's personal and academic information, valid ID and privacy consent (spec 01): a
 * form, then a summary to confirm it all on.
 */
#[Title('My Profile')]
class Profile extends Component
{
    use TrimsInput;
    use WithFileUploads;

    public RequesterProfileForm $form;

    #[Locked]
    public bool $reviewing = false;

    public function mount(): void
    {
        $this->form->load($this->user());
    }

    /**
     * Check the whole form and show the summary; nothing is saved yet (REQ-01-22, REQ-01-25).
     */
    public function review(): void
    {
        $this->form->validateDetails();

        $this->showStep(reviewing: true);
    }

    public function back(): void
    {
        $this->showStep(reviewing: false);
    }

    /**
     * One save for the whole profile, from the summary: personal and academic details, privacy
     * consent and, when chosen, a new valid ID (REQ-01-25).
     */
    public function save(SubmitIdentityDocument $submit): void
    {
        if (! $this->reviewing) {
            return;
        }

        $user = $this->user();

        try {
            $id = $this->form->save($user);
        } catch (ValidationException $e) {
            // Only the consent can be fixed on the summary; any other error shows on the form.
            if (array_keys($e->errors()) !== ['form.privacy_consent']) {
                $this->showStep(reviewing: false);
            }

            throw $e;
        }

        if ($id !== null) {
            try {
                $submit->handle($user, $id['type'], $id['file']);
            } catch (ValidationException $e) {
                $this->addError('form.id_file', $e->validator->errors()->first());
                $this->showStep(reviewing: false);

                return;
            }

            $this->form->reset('id_type', 'id_file');
        }

        $this->showStep(reviewing: false);
        $this->dispatch('notify', message: $id !== null
            ? 'Profile saved. The Office of the Registrar will verify your ID.'
            : 'Profile saved.');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.student.profile', [
            'user' => $user,
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'idTypes' => IdentityDocument::ID_TYPES,
            'documents' => $user->identityDocuments()->get(),
            'canUpload' => $user->identity_status !== IdentityStatus::Verified,
            'summary' => $this->reviewing ? $this->form->summary() : [],
        ]);
    }

    /**
     * Change step and move focus to the step's label, so the page starts from the top and screen
     * readers announce the change.
     */
    private function showStep(bool $reviewing): void
    {
        $this->reviewing = $reviewing;

        $this->js("document.getElementById('profile-step')?.focus()");
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
