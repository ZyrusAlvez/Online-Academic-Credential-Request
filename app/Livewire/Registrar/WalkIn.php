<?php

namespace App\Livewire\Registrar;

use App\Actions\WalkIn\RecordWalkInRequest;
use App\Enums\AcademicStatus;
use App\Enums\Role;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\CredentialRequestForm;
use App\Livewire\Forms\WalkInRequesterForm;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\Program;
use App\Models\Purpose;
use App\Models\User;
use App\Support\OfficeRoute;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Registrar's Walk-in Request (spec 26): New Request's form without the valid ID and Who Will
 * Transact, then a summary with the evaluator's confirmation instead of the privacy consent.
 */
#[Title('Walk-in Request')]
class WalkIn extends Component
{
    use TrimsInput;

    public CredentialRequestForm $form;

    public WalkInRequesterForm $profile;

    /** The evaluator validated the request's eligibility and the requester's details (REQ-26-03). */
    public bool $attested = false;

    #[Locked]
    public bool $reviewing = false;

    public function mount(): void
    {
        abort_unless($this->registrar()->hasRole(Role::Registrar), 403);
    }

    /** Each college has its own programs (REQ-12-07). */
    public function updatedProfileCollegeId(): void
    {
        $this->profile->program = '';
    }

    /**
     * Check every entry and show the summary; every problem is listed at once (REQ-01-26).
     */
    public function review(): void
    {
        $failure = null;

        foreach ([fn () => $this->form->validatedRequest(), fn () => $this->profile->validateDetails()] as $check) {
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
     * Record the request and go to its Payment Validation (REQ-26-04).
     */
    public function submit(RecordWalkInRequest $record): void
    {
        if (! $this->reviewing) {
            $this->review();

            return;
        }

        $this->validate(['attested' => ['accepted']], ['attested.accepted' => 'Confirm that you validated the request and the requester\'s information.']);

        try {
            $items = $this->form->validatedItems();
            $request = $record->handle($this->registrar(), $items, $this->profile->details());
        } catch (InvalidTransition $e) {
            $this->showStep(reviewing: false);
            $this->addError('form.credential_type_id', $e->getMessage());
            Flux::modal('profile-errors')->show();

            return;
        }

        $number = count($items) > 1 ? $request->application()->value('reference_no') : $request->reference_no;
        session()->flash('notify', "Walk-in request {$number} recorded.");
        $this->redirect($request->status->isBeforeAcceptance()
            ? route('registrar.payments.show', $request)
            : route('records.show', $request), navigate: true);
    }

    public function render(): View
    {
        $types = CredentialType::requestable()->with('steps.department')->orderBy('name')->get();
        $selectedType = $types->firstWhere('id', (int) $this->form->credential_type_id);
        $college = filled($this->profile->college_id) ? Department::find((int) $this->profile->college_id) : null;
        $registrarOffice = Department::where('code', Department::REGISTRAR_CODE)->value('name') ?? __('Office of the Registrar');

        return view('livewire.registrar.walk-in', [
            'types' => $types,
            'selectedType' => $selectedType,
            'routes' => $types->mapWithKeys(fn (CredentialType $type): array => [$type->id => OfficeRoute::planned($type, $college, $registrarOffice)])->all(),
            'purposes' => Purpose::active()->orderBy('name')->pluck('name'),
            'statuses' => AcademicStatus::cases(),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'programs' => $college !== null
                ? Program::active()->where('department_id', $college->id)->orderBy('name')->pluck('name')
                : collect(),
            // One section per credential, and the total when there are several (REQ-27-10).
            'requestSummary' => $this->reviewing ? $this->form->summary() : [],
            'summary' => $this->reviewing ? $this->profile->summary() : [],
        ]);
    }

    /** Another credential in the same application (REQ-27-10). */
    public function addCredential(): void
    {
        $this->form->addCredential();
    }

    public function removeCredential(int $index): void
    {
        $this->form->removeCredential($index);
    }

    private function showStep(bool $reviewing): void
    {
        $this->reviewing = $reviewing;

        $this->js("document.getElementById('request-step')?.focus()");
    }

    private function registrar(): User
    {
        /** @var User */
        return Auth::user();
    }
}
