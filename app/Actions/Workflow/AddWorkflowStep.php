<?php

namespace App\Actions\Workflow;

use App\Models\CredentialType;
use App\Models\Department;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddWorkflowStep
{
    /**
     * Append a step. Pass a Department, or null for the "Student's College" placeholder.
     *
     * @throws ValidationException when the department/placeholder is already in the workflow
     */
    public function handle(CredentialType $type, ?Department $department, ?string $instructions = null, string $errorKey = 'department'): WorkflowStep
    {
        return DB::transaction(function () use ($type, $department, $instructions, $errorKey): WorkflowStep {
            CredentialType::whereKey($type->id)->lockForUpdate()->first();

            $steps = $type->steps()->get();

            if ($department === null && $steps->contains('is_student_college', true)) {
                throw ValidationException::withMessages([$errorKey => "This workflow already has a Student's College step."]);
            }

            if ($department !== null && $steps->contains('department_id', $department->id)) {
                throw ValidationException::withMessages([$errorKey => "{$department->name} is already in this workflow."]);
            }

            if ($department !== null && ! $department->is_active) {
                throw ValidationException::withMessages([$errorKey => "{$department->name} is inactive."]);
            }

            return $type->steps()->create([
                'position' => $steps->count() + 1,
                'department_id' => $department?->id,
                'is_student_college' => $department === null,
                'instructions' => filled($instructions) ? $instructions : null,
            ]);
        });
    }
}
