<?php

namespace App\Livewire\Student\Requests;

use App\Actions\Identity\SubmitIdentityDocument;
use App\Enums\AcademicStatus;
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
use App\Models\User;
use App\Services\RequestWorkflow;
use App\Support\EmptyValue;
use App\Support\OfficeRoute;
use App\Support\VerifiedDetails;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $this->authorizeForm();

        // Every section is checked, so the modal lists all that needs attention at once (REQ-01-26).
        $failure = null;

        foreach ([
            fn () => $this->form->validatedRequest(),
            fn () => $this->profile->validateDetails($this->mayReplaceSavedId()),
            fn () => $this->validateRepresentativeFiles(),
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

    /** Another credential in the same application (REQ-27-10). */
    public function addCredential(): void
    {
        // Only as many credentials as the requester has room for (REQ-33-04).
        if (count($this->form->more) + 1 < $this->owner()->openRequestRoom()) {
            $this->form->addCredential();
        }
    }

    public function removeCredential(int $index): void
    {
        $this->form->removeCredential($index);
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

        try {
            $data = $this->form->validatedItems();
            $representative = $this->form->representative();
            $this->validateRepresentativeFiles();
            $documents = $representative !== null ? $this->representativeDocuments() : null;

            $request = DB::transaction(function () use ($owner, $data, $representative, $documents, $workflow, $submitIdentity, &$stored): CredentialRequest {
                // The owner's row is locked so two submissions at once cannot pass the limit together (REQ-33-03).
                User::query()->whereKey($owner->id)->lockForUpdate()->first();
                $this->checkRequestLimit();

                $chosen = $this->profile->save($owner, $this->mayReplaceSavedId());

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

        // An application of several credentials is quoted by its own number (REQ-27-01).
        $number = count($data) > 1 ? $request->application()->value('reference_no') : $request->reference_no;
        session()->flash('notify', "Request {$number} received for evaluation.");
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
            'routes' => $types->mapWithKeys(fn (CredentialType $type): array => [$type->id => OfficeRoute::planned($type, $college, $registrarOffice)])->all(),
            'purposes' => Purpose::active()->orderBy('name')->pluck('name'),
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'programs' => $college !== null
                ? Program::active()->where('department_id', $college->id)->orderBy('name')->pluck('name')
                : collect(),
            'idTypes' => IdentityDocument::ID_TYPES,
            'savedId' => $owner->latestIdentityDocument()->first(),
            'verified' => VerifiedDetails::sections($owner),
            'requestSummary' => $this->reviewing ? $this->requestSummary() : [],
            'transactSummary' => $this->reviewing ? $this->transactSummary() : [],
            'summary' => $this->reviewing ? $this->profile->summary() : [],
            // Edit Request (spec 20) shows the same form for a returned request.
            'editing' => null,
            'room' => $owner->openRequestRoom(),
        ]);
    }

    /**
     * At most OPEN_LIMIT open online requests, each credential one; checked on review and again,
     * under a lock, on submit (REQ-33-02, 03).
     *
     * @throws ValidationException on the first credential
     */
    protected function checkRequestLimit(): void
    {
        $owner = $this->owner();
        $room = $owner->openRequestRoom();

        if (count($this->form->more) + 1 <= $room) {
            return;
        }

        $limit = CredentialRequest::OPEN_LIMIT;

        throw ValidationException::withMessages(['form.credential_type_id' => $room === 0
            ? __('You have :limit open requests, the most allowed at a time. You can request again once one is released, declined or cancelled.', ['limit' => $limit])
            : trans_choice(
                'You can request only :room more credential while you have :open open requests (at most :limit at a time).|You can request only :room more credentials while you have :open open requests (at most :limit at a time).',
                $room,
                ['room' => $room, 'open' => $limit - $room, 'limit' => $limit],
            ),
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
        // Edit Request overrides this: a resubmission is not a new request (REQ-33-05).
        return [fn () => $this->checkRequestLimit()];
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
     * Nothing of a failed submission stays: the database rolled back, so drop what the model
     * still holds and the ID file stored on the way (REQ-15-06).
     */
    protected function undo(User $owner, ?IdentityDocument $stored): void
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
     * Section 1 of the summary, numbered and labelled like the form (REQ-15-05, 17).
     *
     * @return array<string, array<string, string|EmptyValue>>
     */
    protected function requestSummary(): array
    {
        // One section per credential, and the total when there are several (REQ-27-10).
        return $this->form->summary(AcademicStatus::tryFrom($this->profile->academic_status));
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
     * Section 5 of the summary: who submits the documents and claims the credential, the SPA and
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
