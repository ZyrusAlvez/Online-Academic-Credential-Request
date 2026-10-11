<?php

namespace App\Livewire\Registrar\CredentialTypes;

use App\Actions\Workflow\AddWorkflowStep;
use App\Actions\Workflow\MoveWorkflowStep;
use App\Actions\Workflow\RemoveWorkflowStep;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\WorkflowStep;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Workflow extends Component
{
    private const STUDENT_COLLEGE = 'college';

    #[Locked]
    public CredentialType $credentialType;

    /** Department id, or 'college' for the Student's College placeholder. */
    public string $newStep = '';

    public string $newInstructions = '';

    #[Locked]
    public ?int $editingStepId = null;

    public string $editingInstructions = '';

    public function mount(CredentialType $credentialType): void
    {
        $this->authorize('update', $credentialType);

        $this->credentialType = $credentialType;
    }

    public function addStep(AddWorkflowStep $addStep): void
    {
        $this->authorize('update', $this->credentialType);

        $this->validate([
            'newStep' => ['required', 'string'],
            'newInstructions' => ['nullable', 'string', 'max:255'],
        ], ['newStep.required' => 'Choose a department or the Student\'s College.']);

        $department = $this->newStep === self::STUDENT_COLLEGE
            ? null
            : Department::findOrFail((int) $this->newStep);

        $step = $addStep->handle($this->credentialType, $department, $this->newInstructions, errorKey: 'newStep');

        $this->reset('newStep', 'newInstructions');
        $this->dispatch('notify', message: "{$step->label()} added as step {$step->position}.");
    }

    public function move(int $stepId, int $direction, MoveWorkflowStep $move): void
    {
        $this->authorize('update', $this->credentialType);

        $move->handle($this->findStep($stepId), $direction);
    }

    public function remove(int $stepId, RemoveWorkflowStep $remove): void
    {
        $this->authorize('update', $this->credentialType);

        $step = $this->findStep($stepId);
        $remove->handle($step);

        $this->dispatch('notify', message: "{$step->label()} removed from the workflow.");
    }

    public function editInstructions(int $stepId): void
    {
        $this->authorize('update', $this->credentialType);

        $this->editingStepId = $stepId;
        $this->editingInstructions = $this->findStep($stepId)->instructions ?? '';
    }

    public function saveInstructions(): void
    {
        $this->authorize('update', $this->credentialType);

        $this->validate(['editingInstructions' => ['nullable', 'string', 'max:255']]);

        if ($this->editingStepId !== null) {
            $this->findStep($this->editingStepId)->update([
                'instructions' => filled($this->editingInstructions) ? $this->editingInstructions : null,
            ]);
        }

        $this->cancelEditing();
    }

    public function cancelEditing(): void
    {
        $this->reset('editingStepId', 'editingInstructions');
    }

    private function findStep(int $stepId): WorkflowStep
    {
        return $this->credentialType->steps()->with('department')->findOrFail($stepId);
    }

    public function render(): View
    {
        $steps = $this->credentialType->steps()->with('department')->get();

        return view('livewire.registrar.credential-types.workflow', [
            'steps' => $steps,
            'hasCollegeStep' => $steps->contains('is_student_college', true),
            'availableDepartments' => Department::active()
                ->whereNotIn('id', $steps->pluck('department_id')->filter())
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ])->title("Workflow · {$this->credentialType->name}");
    }
}
