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
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The requester's personal and academic information, valid ID and privacy consent (spec 01).
 */
#[Title('My Profile')]
class Profile extends Component
{
    use TrimsInput;
    use WithFileUploads;

    public RequesterProfileForm $form;

    public function mount(): void
    {
        $this->form->load($this->user());
    }

    /**
     * One save for the whole profile: personal and academic details, privacy consent and, when
     * chosen, a new valid ID (REQ-01-22).
     */
    public function save(SubmitIdentityDocument $submit): void
    {
        $user = $this->user();
        $id = $this->form->save($user);

        if ($id !== null) {
            try {
                $submit->handle($user, $id['type'], $id['file']);
            } catch (ValidationException $e) {
                $this->addError('form.id_file', $e->validator->errors()->first());

                return;
            }

            $this->form->reset('id_type', 'id_file');
        }

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
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
