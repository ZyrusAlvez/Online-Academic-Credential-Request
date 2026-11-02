<?php

namespace App\Livewire\Registrar\CredentialTypes;

use App\Actions\Workflow\AddWorkflowStep;
use App\Actions\Workflow\MoveWorkflowStep;
use App\Actions\Workflow\RemoveWorkflowStep;
use App\Enums\RouteTask;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\WorkflowStep;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Workflow extends Component
{
    use TrimsInput;

    private const STUDENT_COLLEGE = 'college';

    #[Locked]
    public CredentialType $credentialType;

    /** Department id, or 'college' for the Dean (student's college) placeholder. */
    public string $newStep = '';

    public string $newTask = 'sign';

    public string $newInstructions = '';

    #[Locked]
    public ?int $editingStepId = null;

    public string $editingTask = 'sign';

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
            'newTask' => ['required', Rule::enum(RouteTask::class)->only(RouteTask::configurable())],
            'newInstructions' => ['nullable', 'string', 'max:255'],
        ], ['newStep.required' => 'Choose an office or the Dean.']);

        $department = $this->newStep === self::STUDENT_COLLEGE
            ? null
            : Department::findOrFail((int) $this->newStep);

        $step = $addStep->handle($this->credentialType, $department, RouteTask::from($this->newTask), $this->newInstructions, errorKey: 'newStep');

        $this->reset('newStep', 'newTask', 'newInstructions');
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

        $this->dispatch('notify', message: "{$step->label()} removed from the route.");
    }

    public function editStep(int $stepId): void
    {
        $this->authorize('update', $this->credentialType);

        $step = $this->findStep($stepId);
        $this->editingStepId = $stepId;
        $this->editingTask = $step->task->value;
        $this->editingInstructions = $step->instructions ?? '';
    }

    public function saveStep(): void
    {
        $this->authorize('update', $this->credentialType);

        $this->validate([
            'editingTask' => ['required', Rule::enum(RouteTask::class)->only(RouteTask::configurable())],
            'editingInstructions' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->editingStepId !== null) {
            $this->findStep($this->editingStepId)->update([
                'task' => RouteTask::from($this->editingTask),
                'instructions' => filled($this->editingInstructions) ? $this->editingInstructions : null,
            ]);
        }

        $this->cancelEditing();
    }

    public function cancelEditing(): void
    {
        $this->reset('editingStepId', 'editingTask', 'editingInstructions');
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
            'tasks' => RouteTask::configurable(),
            'availableDepartments' => Department::active()
                ->whereNotIn('id', $steps->pluck('department_id')->filter())
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ])->title("Route · {$this->credentialType->name}");
    }
}
