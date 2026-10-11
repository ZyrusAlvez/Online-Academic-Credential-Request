<?php

namespace Database\Factories;

use App\Models\CredentialType;
use App\Models\Department;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credential_type_id' => CredentialType::factory(),
            'position' => 1,
            'department_id' => Department::factory(),
            'is_student_college' => false,
            'instructions' => null,
        ];
    }
}
