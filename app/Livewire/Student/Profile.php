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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The requester's academic information and valid ID (spec 01).
 */
#[Title('My Profile')]
class Profile extends Component
{
    use TrimsInput;
    use WithFileUploads;

    public RequesterProfileForm $form;

    public string $idType = '';

    /** @var UploadedFile|null */
    public $idFile = null;

    public function mount(): void
    {
        $this->form->load($this->user());
    }

    public function save(): void
    {
        $this->form->save($this->user());

        $this->dispatch('notify', message: 'Profile saved.');
    }

    public function uploadId(SubmitIdentityDocument $submit): void
    {
        $this->validate([
            'idType' => ['required', Rule::in(IdentityDocument::ID_TYPES)],
            'idFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], attributes: ['idType' => 'type of ID', 'idFile' => 'valid ID']);

        /** @var UploadedFile $file */
        $file = $this->idFile;
        $submit->handle($this->user(), $this->idType, $file);

        $this->reset('idType', 'idFile');
        $this->dispatch('notify', message: 'Valid ID uploaded. The Office of the Registrar will verify it.');
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
