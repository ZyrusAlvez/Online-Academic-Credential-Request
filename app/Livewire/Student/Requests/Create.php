<?php

namespace App\Livewire\Student\Requests;

use App\Enums\RouteTask;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\CredentialRequestForm;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\Purpose;
use App\Models\User;
use App\Models\WorkflowStep;
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

        if (! $this->owner()->canRequestCredentials()) {
            $this->dispatch('notify', message: 'Complete your profile and upload a valid ID before requesting a credential.');

            return;
        }

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
        $owner = $this->owner()->loadMissing(['college', 'latestIdentityDocument']);

        $selectedType = $types->firstWhere('id', (int) $this->form->credential_type_id);

        return view('livewire.student.requests.create', [
            'types' => $types,
            'selectedType' => $selectedType,
            'route' => $selectedType ? $this->route($selectedType, $owner) : [],
            'purposes' => Purpose::active()->orderBy('name')->pluck('name'),
            'owner' => $owner,
            'locked' => ! $owner->canRequestCredentials(),
        ]);
    }

    /**
     * The offices a credential passes through, ending with the Registrar's final check (REQ-09-11).
     *
     * @return list<array{office: string, task: string}>
     */
    private function route(CredentialType $type, User $owner): array
    {
        $route = $type->steps->map(fn (WorkflowStep $step): array => [
            'office' => $step->is_student_college
                ? ($owner->college ? __('Dean, :college', ['college' => $owner->college->name]) : __('Dean of your college'))
                : (string) $step->department?->name,
            'task' => $step->task->label(),
        ])->values()->all();

        $route[] = [
            'office' => Department::where('code', Department::REGISTRAR_CODE)->value('name') ?? __('Office of the Registrar'),
            'task' => RouteTask::FinalCheck->label(),
        ];

        return $route;
    }

    private function owner(): User
    {
        /** @var User */
        return Auth::user();
    }
}
