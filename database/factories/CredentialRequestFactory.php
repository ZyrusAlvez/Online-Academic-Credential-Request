<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\RequestStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates requests directly in a given status (bypassing the workflow) for tests and demos.
 * Use RequestWorkflow to exercise real transitions.
 *
 * @extends Factory<CredentialRequest>
 */
class CredentialRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'credential_type_id' => CredentialType::factory(),
            'college_id' => Department::factory()->college(),
            'academic_status' => AcademicStatus::Graduate,
            'program' => 'BS '.fake()->words(2, true),
            'student_number' => fake()->numerify('##-####-###'),
            'attended_from' => 2019,
            'attended_to' => 2023,
            'purpose' => fake()->sentence(),
            'copies' => 1,
            'requires_payment' => false,
            'fee' => null,
            'amount_due' => null,
            'status' => RequestStatus::Submitted,
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CredentialRequest $request): void {
            $request->forceFill([
                'reference_no' => sprintf('CR-%s-%06d', $request->created_at->format('Y'), $request->id),
            ])->save();
        });
    }

    public function status(RequestStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
