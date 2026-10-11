<?php

namespace Database\Factories;

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
     * Attach workflow steps in order. Pass Department models, or the string 'college'
     * for a Student's College step.
     *
     * @param  list<Department|string>  $steps
     */
    public function withSteps(array $steps): static
    {
        return $this->afterCreating(function (CredentialType $type) use ($steps): void {
            foreach ($steps as $index => $step) {
                $type->steps()->create([
                    'position' => $index + 1,
                    'department_id' => $step instanceof Department ? $step->id : null,
                    'is_student_college' => $step === 'college',
                ]);
            }
        });
    }
}
