<?php

namespace App\Livewire\Student\Requests;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
use App\Enums\RouteTask;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\RemovesSavedId;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Concerns\UpdatesFieldErrors;
use App\Livewire\Concerns\ValidatesRepresentativeDocuments;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Forms\RequesterProfileForm;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\Program;
use App\Models\Purpose;
use App\Models\RequestDocument;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Services\RequestWorkflow;
use App\Support\EmptyValue;
use App\Support\VerifiedDetails;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
    use RemovesSavedId;
    use TrimsInput;
    use UpdatesFieldErrors;
    use ValidatesRepresentativeDocuments;
    use WithFileUploads;

    public CredentialRequestForm $form;

    public RequesterProfileForm $profile;

    #[Locked]
    public bool $reviewing = false;

    /**
     * Copies of the documentary requirements: credential type id => requirement index => files
     * (REQ-04-18). Keyed by credential so switching credentials never moves a file to another
     * credential's requirement; only the chosen credential's files are checked and sent.
     *
     * @var array<int|string, array<int|string, mixed>>
     */
    public array $requirementFiles = [];

    /**
     * How often each requirement's files were removed: part of the field's key, so the file
     * picker starts empty again.
     *
     * @var array<string, int>
     */
    #[Locked]
    public array $requirementResets = [];

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
     * Check a requirement's files as soon as they are chosen (REQ-04-18). The key is
     * "<credential type id>.<requirement index>".
     */
    public function updatedRequirementFiles(mixed $value, string $key): void
    {
        [$typeId, $index] = array_map(intval(...), array_pad(explode('.', $key), 2, '0'));

        $this->checkRequirementFiles($typeId, $index);
    }

    /**
     * Drop one of the files chosen for a requirement; the others stay (REQ-04-18).
     */
    public function removeRequirementFile(int $typeId, int $index, int $file): void
    {
        $field = "requirementFiles.{$typeId}.{$index}";
        $files = (array) ($this->requirementFiles[$typeId][$index] ?? []);

        unset($files[$file]);

        if ($files === []) {
            unset($this->requirementFiles[$typeId][$index]);
        } else {
            $this->requirementFiles[$typeId][$index] = array_values($files);
        }

        // A new key empties the file picker, so the same file can be chosen again.
        $this->requirementResets[$field] = ($this->requirementResets[$field] ?? 0) + 1;

        $this->checkRequirementFiles($typeId, $index);
    }

    private function checkRequirementFiles(int $typeId, int $index): void
    {
        $requirement = CredentialType::requestable()->find($typeId)?->requirements[$index] ?? null;
        $field = "requirementFiles.{$typeId}.{$index}";

        $this->resetErrorBag($field);

        if ($requirement !== null && ($message = $this->requirementFilesError($typeId, $index, $requirement)) !== null) {
            $this->addError($field, $message);
        }
    }

    /**
     * Check every entry and show the summary; nothing is saved yet (REQ-15-05).
     */
    public function review(): void
    {
        $this->authorizeForm();

        // Every section is checked, so the modal lists all that needs attention at once (REQ-01-26).
        $failure = null;

        foreach ([
            fn () => $this->form->validatedRequest(),
            fn () => $this->profile->validateDetails($this->mayReplaceSavedId()),
            fn () => $this->validateRepresentativeFiles(),
            fn () => $this->validateRequirementFiles(),
            ...$this->extraChecks(),
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

        $this->authorizeForm();

        $owner = $this->owner();
        $stored = null;
        $requirementDocuments = [];

        try {
            $data = $this->form->validatedRequest();
            $representative = $this->form->representative();
            $this->validateRepresentativeFiles();
            $this->validateRequirementFiles();
            $documents = $representative !== null ? $this->representativeDocuments() : null;
            $requirementFiles = $this->chosenRequirementFiles();

            $request = DB::transaction(function () use ($owner, $data, $representative, $documents, $requirementFiles, $workflow, $submitIdentity, &$stored, &$requirementDocuments): CredentialRequest {
                $chosen = $this->profile->save($owner, $this->mayReplaceSavedId());

                if ($chosen !== null) {
                    $stored = $submitIdentity->handle($owner, $chosen['type'], $chosen['file']);
                }

                $request = $workflow->submit($owner, $data, $stored);

                if ($requirementFiles !== []) {
                    $requirementDocuments = $workflow->attachRequirementDocuments($request, $owner, $requirementFiles);
                }

                if ($representative !== null && $documents !== null) {
                    $workflow->declareRepresentative($request, $owner, $representative, ...$documents);
                }

                return $request;
            });
        } catch (Throwable $e) {
            $this->undo($owner, $stored, $requirementDocuments);
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
            'verified' => VerifiedDetails::sections($owner),
            'requestSummary' => $this->reviewing ? $this->requestSummary($selectedType) : [],
            'transactSummary' => $this->reviewing ? $this->transactSummary() : [],
            'summary' => $this->reviewing ? $this->profile->summary() : [],
            // Edit Request (spec 20) shows the same form for a returned request.
            'editing' => null,
        ]);
    }

    /**
     * Who may use the form: any requester for a new request; Edit narrows it (REQ-20-05).
     */
    protected function authorizeForm(): void
    {
        $this->authorize('create', CredentialRequest::class);
    }

    /**
     * Whether a new ID may be given although one is saved (REQ-15-04): never on New Request.
     */
    protected function mayReplaceSavedId(): bool
    {
        return false;
    }

    /**
     * Further checks run with the others before the summary.
     *
     * @return list<callable(): mixed>
     */
    protected function extraChecks(): array
    {
        return [];
    }

    /**
     * The SPA and the representative's ID, needed only when transacting through one (REQ-11-01).
     */
    protected function validateRepresentativeFiles(): void
    {
        if ($this->form->representative() === null) {
            return;
        }

        $this->validate($this->representativeDocumentRules(), $this->representativeDocumentMessages(), $this->representativeDocumentAttributes());
    }

    /**
     * The chosen credential's requirement files, each checked: JPG, PNG, WebP or PDF, 5 MB each,
     * at most RequestDocument::MAX_PER_REQUIREMENT per requirement (REQ-04-18).
     *
     * @throws ValidationException listing every requirement whose files need attention
     */
    protected function validateRequirementFiles(): void
    {
        $type = $this->chosenType();
        $errors = [];

        if ($type === null) {
            return;
        }

        foreach ($type->requirements ?? [] as $index => $requirement) {
            $message = $this->requirementFilesError($type->id, $index, $requirement);

            if ($message !== null) {
                $errors["requirementFiles.{$type->id}.{$index}"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The first problem with the files chosen for one requirement, or null when they are fine
     * (or there are none: every requirement is optional).
     */
    private function requirementFilesError(int $typeId, int $index, string $requirement): ?string
    {
        $files = $this->requirementFiles[$typeId][$index] ?? [];
        $max = RequestDocument::MAX_PER_REQUIREMENT;

        $validator = Validator::make(['files' => is_array($files) ? array_values($files) : $files], [
            'files' => ['array', "max:{$max}"],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'files.array' => 'Choose the files for “:requirement” again.',
            'files.max' => 'Upload at most :max files for “:requirement”.',
            'files.*.file' => 'Each file for “:requirement” must be a file.',
            'files.*.uploaded' => 'A file for “:requirement” could not be uploaded. Use a JPG, PNG, WebP or PDF file of 5 MB or smaller.',
            'files.*.mimes' => 'Each file for “:requirement” must be a JPG, PNG, WebP or PDF file.',
            'files.*.max' => 'Each file for “:requirement” must be 5 MB or smaller.',
        ]);

        $message = $validator->errors()->first();

        return $message === '' ? null : str_replace(':requirement', $requirement, $message);
    }

    /**
     * The chosen credential's requirement files after validation, by requirement, for the workflow.
     *
     * @return array<string, list<UploadedFile>>
     */
    protected function chosenRequirementFiles(): array
    {
        $type = $this->chosenType();
        $files = [];

        if ($type === null) {
            return [];
        }

        foreach ($type->requirements ?? [] as $index => $requirement) {
            $chosen = array_filter((array) ($this->requirementFiles[$type->id][$index] ?? []), fn ($file): bool => $file instanceof UploadedFile);

            if ($chosen !== []) {
                $files[$requirement] = [...($files[$requirement] ?? []), ...array_values($chosen)];
            }
        }

        return $files;
    }

    private function chosenType(): ?CredentialType
    {
        return filled($this->form->credential_type_id) ? CredentialType::requestable()->find((int) $this->form->credential_type_id) : null;
    }

    /**
     * Nothing of a failed submission stays: the database rolled back, so drop what the model
     * still holds and the files stored on the way (REQ-15-06, REQ-04-18).
     *
     * @param  list<RequestDocument>  $requirementDocuments
     */
    protected function undo(User $owner, ?IdentityDocument $stored, array $requirementDocuments = []): void
    {
        $owner->refresh();

        if ($stored !== null) {
            Storage::disk(RequestWorkflow::proofDisk())->delete($stored->path);
        }

        if ($requirementDocuments !== []) {
            Storage::disk(RequestWorkflow::proofDisk())->delete(array_map(fn (RequestDocument $document): string => $document->path, $requirementDocuments));
        }
    }

    /**
     * Back to the form with the error, except a missing consent, which is ticked on the summary.
     *
     * @throws ValidationException the entries' own errors, shown where they belong
     * @throws Throwable anything that is not a validation or workflow error
     */
    protected function showFailure(Throwable $e): void
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
     * Sections 1 and 2 of the summary, numbered and labelled like the form (REQ-15-05, 17).
     *
     * @return array<string, array<string, string|EmptyValue|list<string|array{name: string, size: string, href: string, download: string, type: string}>>>
     */
    protected function requestSummary(?CredentialType $type): array
    {
        $purpose = $this->form->purpose === CredentialRequestForm::OTHER_PURPOSE ? $this->form->purpose_other : $this->form->purpose;
        $receivingLabel = $type?->requires_receiving_institution ? __('Receiving school') : __('Receiving school, employer or agency');

        return [
            __('1. What Do You Need?') => [
                __('Credential') => (string) $type?->name,
                __('Purpose') => $purpose,
                // Left blank: shown as a grey "Not provided", not as an entry (REQ-15-19).
                $receivingLabel => filled($this->form->receiving_institution) ? $this->form->receiving_institution : EmptyValue::notProvided(),
            ],
            __('2. Documentary Requirements') => $this->requirementSummary($type),
        ];
    }

    /**
     * Every requirement of the credential with the names and sizes of the files chosen for it, each
     * name a link to the file viewer (REQ-15-17).
     *
     * @return array<string, string|EmptyValue|list<string|array{name: string, size: string, href: string, download: string, type: string}>>
     */
    private function requirementSummary(?CredentialType $type): array
    {
        if ($type === null || ! $type->requirements) {
            return [__('Requirements') => new EmptyValue(__('None for this credential'))];
        }

        $rows = [];

        foreach ($type->requirements as $index => $requirement) {
            $files = array_filter((array) ($this->requirementFiles[$type->id][$index] ?? []), fn ($file): bool => $file instanceof UploadedFile);

            $rows[$requirement] = $files === []
                ? $this->noRequirementFiles()
                : array_map($this->summaryFile(...), array_values($files));
        }

        return $rows;
    }

    /**
     * A chosen file on the summary: its name, linked to a signed, short-lived preview that the file
     * viewer opens, and its size. The preview also serves the download: it already answers as one,
     * and an added `?download=1` would break its signature (REQ-09-14). A file Livewire cannot
     * preview is its name and size only.
     *
     * @return string|array{name: string, size: string, href: string, download: string, type: string}
     */
    private function summaryFile(UploadedFile $file): string|array
    {
        $name = $file->getClientOriginalName();
        $size = Number::fileSize((int) $file->getSize(), maxPrecision: 1);

        if (! $file instanceof TemporaryUploadedFile || ! $file->isPreviewable()) {
            return $name.' ('.$size.')';
        }

        $preview = $file->temporaryUrl();

        return ['name' => $name, 'size' => $size, 'href' => $preview, 'download' => $preview, 'type' => (string) $file->getMimeType()];
    }

    /**
     * What section 2 of the summary says for a requirement without files, in grey (REQ-15-19).
     */
    protected function noRequirementFiles(): EmptyValue
    {
        return new EmptyValue(__('No files'));
    }

    /**
     * Section 6 of the summary: who submits the documents and claims the credential, the SPA and
     * the representative's ID linked to the file viewer (REQ-15-17).
     *
     * @return array<string, string|EmptyValue|list<string|array{name: string, size: string, href: string, download: string, type: string}>>
     */
    protected function transactSummary(): array
    {
        $representative = $this->form->representative();

        if ($representative === null) {
            return [__('Transacting') => __('Personally')];
        }

        return [
            __('Transacting') => __('Through an authorized representative'),
            __('Representative\'s full name') => $representative['full_name'],
            // Optional fields left blank show a grey "Not provided" (REQ-15-19).
            __('Relationship to you') => $representative['relationship'] ?? EmptyValue::notProvided(),
            __('Representative\'s contact number') => $representative['contact_number'] ?? EmptyValue::notProvided(),
            __('SPA') => $this->spa instanceof UploadedFile ? [$this->summaryFile($this->spa)] : '',
            __('Representative\'s valid ID') => $this->representativeId instanceof UploadedFile ? [$this->summaryFile($this->representativeId)] : '',
        ];
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
    protected function showStep(bool $reviewing): void
    {
        $this->reviewing = $reviewing;

        $this->js("document.getElementById('request-step')?.focus()");
    }

    protected function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
