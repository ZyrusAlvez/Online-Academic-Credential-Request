<?php

namespace Database\Factories;

use App\Enums\RouteTask;
use App\Models\CredentialType;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CredentialType>
 */
class CredentialTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Certificate of '.fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'requires_payment' => false,
            'fee' => null,
            'processing_days' => 5,
            'is_active' => true,
        ];
    }

    public function paid(string $fee = '150.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_payment' => true,
            'fee' => $fee,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach route steps in order. Pass Department models, [Department, RouteTask] pairs, or the
     * string 'college' for the Dean step. Tasks default to sign.
     *
     * @param  list<Department|string|array{Department, RouteTask}>  $steps
     */
    public function withSteps(array $steps): static
    {
        return $this->afterCreating(function (CredentialType $type) use ($steps): void {
            foreach ($steps as $index => $step) {
                [$department, $task] = is_array($step) ? $step : [$step, RouteTask::Sign];

                $type->steps()->create([
                    'position' => $index + 1,
                    'department_id' => $department instanceof Department ? $department->id : null,
                    'is_student_college' => $department === 'college',
                    'task' => $task,
                ]);
            }
        });
    }
}
