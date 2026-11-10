<?php

namespace App\Livewire\Student\Requests;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\RouteTask;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Concerns\ValidatesRepresentativeDocuments;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\Program;
use App\Models\Purpose;
use App\Models\User;
use App\Models\WorkflowStep;
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
 * Request a credential (spec 04) with the requester's details and valid ID on the same form,
 * filled in from their saved profile (spec 15); optionally declare a representative, with their
 * SPA and valid ID (spec 11).
 * A form, then a summary to submit from.
 */
#[Title('New Request')]
class Create extends Component
{
    use TrimsInput;
    use ValidatesRepresentativeDocuments;
    use WithFileUploads;

    public CredentialRequestForm $form;

    public RequesterProfileForm $profile;

    #[Locked]
    public bool $reviewing = false;

    public function mount(): void
    {
        $this->authorize('create', CredentialRequest::class);

        $this->profile->load($this->owner());
    }

    /**
     * Each college has its own programs, so a new college needs a new choice (REQ-12-07).
     */
    public function updatedProfileCollegeId(): void
    {
        $this->profile->program = '';
    }

    /**
     * Check every entry and show the summary; nothing is saved yet (REQ-15-05).
     */
    public function review(): void
    {
        $this->authorize('create', CredentialRequest::class);

        // Every section is checked, so the modal lists all that needs attention at once (REQ-01-26).
        $failure = null;

        foreach ([
            fn () => $this->form->validatedRequest(),
            fn () => $this->profile->validateDetails(),
            fn () => $this->validateRepresentativeFiles(),
        ] as $check) {
            try {
                $check();
            } catch (ValidationException $e) {
                if ($failure === null) {
                    $failure = $e;
                } else {
                    $failure->validator->errors()->merge($e->errors());
                }
            }
        }

        if ($failure !== null) {
            Flux::modal('profile-errors')->show();

            throw $failure;
        }

        $this->showStep(reviewing: true);
    }

    public function back(): void
    {
        $this->showStep(reviewing: false);
    }

    /**
     * Save the details to the profile and submit the request with its copy of them, all or
     * nothing (REQ-15-06).
     */
    public function submit(RequestWorkflow $workflow, SubmitIdentityDocument $submitIdentity): void
    {
        // Only the summary submits; from the form this is a review (REQ-15-05).
        if (! $this->reviewing) {
            $this->review();

            return;
        }

        $this->authorize('create', CredentialRequest::class);

        $owner = $this->owner();
        $stored = null;

        try {
            $data = $this->form->validatedRequest();
            $representative = $this->form->representative();
            $this->validateRepresentativeFiles();
            $documents = $representative !== null ? $this->representativeDocuments() : null;

            $request = DB::transaction(function () use ($owner, $data, $representative, $documents, $workflow, $submitIdentity, &$stored): CredentialRequest {
                $chosen = $this->profile->save($owner);

                if ($chosen !== null) {
                    $stored = $submitIdentity->handle($owner, $chosen['type'], $chosen['file']);
                }

                $request = $workflow->submit($owner, $data, $stored);

                if ($representative !== null && $documents !== null) {
                    $workflow->declareRepresentative($request, $owner, $representative, ...$documents);
                }

                return $request;
            });
        } catch (Throwable $e) {
            $this->undo($owner, $stored);
            $this->showFailure($e);

            return;
        }

        session()->flash('notify', "Request {$request->reference_no} received for evaluation.");
        $this->redirectRoute('student.requests.show', $request, navigate: true);
    }

    public function render(): View
    {
        $owner = $this->owner();
        $types = CredentialType::requestable()->with('steps.department')->orderBy('name')->get();
        $selectedType = $types->firstWhere('id', (int) $this->form->credential_type_id);
        $college = filled($this->profile->college_id) ? Department::find((int) $this->profile->college_id) : null;
        $registrarOffice = Department::where('code', Department::REGISTRAR_CODE)->value('name') ?? __('Office of the Registrar');

        return view('livewire.student.requests.create', [
            'owner' => $owner,
            'types' => $types,
            'selectedType' => $selectedType,
            // Every credential's route is on the page so the chosen one shows at once (REQ-15-15).
            'routes' => $types->mapWithKeys(fn (CredentialType $type): array => [$type->id => $this->route($type, $college, $registrarOffice)])->all(),
            'purposes' => Purpose::active()->orderBy('name')->pluck('name'),
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'programs' => $college !== null
                ? Program::active()->where('department_id', $college->id)->orderBy('name')->pluck('name')
                : collect(),
            'idTypes' => IdentityDocument::ID_TYPES,
            'savedId' => $owner->latestIdentityDocument()->first(),
            'requestSummary' => $this->reviewing ? $this->requestSummary($selectedType) : [],
            'summary' => $this->reviewing ? $this->profile->summary() : [],
        ]);
    }

    /**
     * The SPA and the representative's ID, needed only when transacting through one (REQ-11-01).
     */
    private function validateRepresentativeFiles(): void
    {
        if ($this->form->representative() === null) {
            return;
        }

        $this->validate($this->representativeDocumentRules(), $this->representativeDocumentMessages(), $this->representativeDocumentAttributes());
    }

    /**
     * Nothing of a failed submission stays: the database rolled back, so drop what the model
     * still holds and the ID file stored on the way (REQ-15-06).
     */
    private function undo(User $owner, ?IdentityDocument $stored): void
    {
        $owner->refresh();

        if ($stored !== null) {
            Storage::disk(RequestWorkflow::proofDisk())->delete($stored->path);
        }
    }

    /**
     * Back to the form with the error, except a missing consent, which is ticked on the summary.
     *
     * @throws ValidationException the entries' own errors, shown where they belong
     * @throws Throwable anything that is not a validation or workflow error
     */
    private function showFailure(Throwable $e): void
    {
        $consentOnly = $e instanceof ValidationException && array_keys($e->errors()) === ['profile.privacy_consent'];

        if (! $consentOnly) {
            $this->showStep(reviewing: false);
            Flux::modal('profile-errors')->show();
        }

        if ($e instanceof InvalidTransition) {
            $this->addError('form.credential_type_id', $e->getMessage());
        } elseif ($e instanceof ValidationException && isset($e->errors()['idFile'])) {
            $this->addError('profile.id_file', $e->validator->errors()->first());
        } else {
            throw $e;
        }
    }

    /**
     * The request's own entries as the summary shows them (REQ-15-05).
     *
     * @return array<string, string>
     */
    private function requestSummary(?CredentialType $type): array
    {
        $purpose = $this->form->purpose === CredentialRequestForm::OTHER_PURPOSE ? $this->form->purpose_other : $this->form->purpose;
        $representative = $this->form->representative();

        return array_filter([
            'Credential' => (string) $type?->name,
            'Purpose' => $purpose,
            'Receiving school, employer or agency' => $this->form->receiving_institution,
            'Transacting' => $representative !== null
                ? __('Through :name, your authorized representative', ['name' => $representative['full_name']])
                : __('Personally'),
        ], filled(...));
    }

    /**
     * The offices a credential passes through, ending with the Registrar's final check (REQ-09-11).
     *
     * @return list<array{office: string, task: string}>
     */
    private function route(CredentialType $type, ?Department $college, string $registrarOffice): array
    {
        $route = $type->steps->map(fn (WorkflowStep $step): array => [
            'office' => $step->is_student_college
                ? ($college ? __('Dean, :college', ['college' => $college->name]) : __('Dean of your college'))
                : (string) $step->department?->name,
            'task' => $step->task->label(),
        ])->values()->all();

        $route[] = [
            'office' => $registrarOffice,
            'task' => RouteTask::FinalCheck->label(),
        ];

        return $route;
    }

    /**
     * Change step and move focus to the step's label, so the page starts from the top and screen
     * readers announce the change.
     */
    private function showStep(bool $reviewing): void
    {
        $this->reviewing = $reviewing;

        $this->js("document.getElementById('request-step')?.focus()");
    }

    private function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
