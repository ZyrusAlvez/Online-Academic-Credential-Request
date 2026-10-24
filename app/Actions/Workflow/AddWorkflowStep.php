<?php

namespace App\Actions\Workflow;

use App\Enums\RouteTask;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddWorkflowStep
{
    /**
     * Append a step. Pass a Department, or null for the Dean (student's college) placeholder.
     *
     * @throws ValidationException when the department/placeholder is already in the workflow
     */
    public function handle(CredentialType $type, ?Department $department, RouteTask $task = RouteTask::Sign, ?string $instructions = null, string $errorKey = 'department'): WorkflowStep
    {
        return DB::transaction(function () use ($type, $department, $task, $instructions, $errorKey): WorkflowStep {
            CredentialType::whereKey($type->id)->lockForUpdate()->first();

            $steps = $type->steps()->get();

            if ($department === null && $steps->contains('is_student_college', true)) {
                throw ValidationException::withMessages([$errorKey => 'This route already has a Dean step.']);
            }

            if ($department !== null && $steps->contains('department_id', $department->id)) {
                throw ValidationException::withMessages([$errorKey => "{$department->name} is already in this route."]);
            }

            if ($department !== null && ! $department->is_active) {
                throw ValidationException::withMessages([$errorKey => "{$department->name} is inactive."]);
            }

            return $type->steps()->create([
                'position' => $steps->count() + 1,
                'department_id' => $department?->id,
                'is_student_college' => $department === null,
                'task' => $task,
                'instructions' => filled($instructions) ? $instructions : null,
            ]);
        });
    }
}
