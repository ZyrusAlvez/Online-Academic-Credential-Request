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
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Settings → Profile (REQ-15-07): the saved personal and academic information and valid ID that
 * fill in the next request. A form, then a summary to confirm it on; never locked, as submitted
 * requests keep their own copy (REQ-15-08).
 */
#[Title('Profile')]
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
     * Check the whole form and show the summary; nothing is saved yet (REQ-01-26).
     */
    public function review(): void
    {
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
     * One save for the whole profile, from the summary: details, privacy consent and, when chosen,
     * a new valid ID, all or nothing (REQ-01-29).
     */
    public function save(SubmitIdentityDocument $submit): void
    {
        if (! $this->reviewing) {
            return;
        }

        $user = $this->user();
        $stored = null;

        try {
            DB::transaction(function () use ($user, $submit, &$stored): void {
                $id = $this->form->save($user);

                if ($id !== null) {
                    $stored = $submit->handle($user, $id['type'], $id['file']);
                }
            });
        } catch (Throwable $e) {
            $user->refresh();   // the model still holds what the rolled-back save filled in

            if ($stored !== null) {
                Storage::disk(RequestWorkflow::proofDisk())->delete($stored->path);
            }

            if (! $e instanceof ValidationException) {
                throw $e;
            }

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

        $this->form->reset('id_type', 'id_file');
        $this->showStep(reviewing: false);
        $this->dispatch('notify', message: 'Profile saved. Your next request will use these details.');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.student.profile', [
            'user' => $user,
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'programs' => filled($this->form->college_id)
                ? Program::active()->where('department_id', (int) $this->form->college_id)->orderBy('name')->pluck('name')
                : collect(),
            'idTypes' => IdentityDocument::ID_TYPES,
            'savedId' => $user->latestIdentityDocument()->first(),
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
