<?php

namespace App\Livewire\Student;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\Program;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Each college has its own programs, so a new college needs a new choice (REQ-12-07).
     */
    public function updatedFormCollegeId(): void
    {
        $this->form->program = '';
    }

    /**
     * Check the whole form and show the summary; nothing is saved yet (REQ-01-22, REQ-01-25).
     */
    public function review(): void
    {
        $this->ensureEditable();

        try {
            $this->form->validateDetails();
        } catch (ValidationException $e) {
            Flux::modal('profile-errors')->show();

            throw $e;
        }

        $this->showStep(reviewing: true);
    }

    public function back(): void
    {
        $this->showStep(reviewing: false);
    }

    /**
     * One save for the whole profile, from the summary: personal and academic details, privacy
     * consent and, when chosen, a new valid ID, all or nothing (REQ-01-25, REQ-01-29).
     */
    public function save(SubmitIdentityDocument $submit): void
    {
        if (! $this->reviewing) {
            return;
        }

        $this->ensureEditable();

        $user = $this->user();

        try {
            $id = DB::transaction(function () use ($user, $submit): ?array {
                $id = $this->form->save($user);

                if ($id !== null) {
                    $submit->handle($user, $id['type'], $id['file']);
                }

                return $id;
            });
        } catch (ValidationException $e) {
            // The model still holds what the rolled-back save filled in.
            $user->refresh();

            if (isset($e->errors()['idFile'])) {
                $this->addError('form.id_file', $e->validator->errors()->first());
                $this->showStep(reviewing: false);
                Flux::modal('profile-errors')->show();

                return;
            }

            // Only the consent can be fixed on the summary; any other error shows on the form.
            if (array_keys($e->errors()) !== ['form.privacy_consent']) {
                $this->showStep(reviewing: false);
                Flux::modal('profile-errors')->show();
            }

            throw $e;
        }

        if ($id !== null) {
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
        $locked = $user->hasLockedProfile();   // the identity check is pending or passed (REQ-01-30)

        return view('livewire.student.profile', [
            'user' => $user,
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'programs' => filled($this->form->college_id)
                ? Program::active()->where('department_id', (int) $this->form->college_id)->orderBy('name')->pluck('name')
                : collect(),
            'idTypes' => IdentityDocument::ID_TYPES,
            'documents' => $user->identityDocuments()->get(),
            'canUpload' => $user->needsValidId(),   // no ID yet, or the last one rejected (REQ-01-17)
            'locked' => $locked,
            // The form holds the saved profile while locked, as nothing can be typed into it.
            'details' => $locked ? $this->form->summary() : [],
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

    /**
     * A pending or verified identity check can't be changed from here (REQ-01-30).
     */
    private function ensureEditable(): void
    {
        abort_if($this->user()->hasLockedProfile(), 403, 'Your profile is locked while your identity check is pending or verified.');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
