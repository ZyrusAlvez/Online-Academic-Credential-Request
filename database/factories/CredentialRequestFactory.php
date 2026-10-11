<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\StudentStatus;
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
        $year = (int) fake()->numberBetween(2010, 2025);

        return [
            'user_id' => User::factory()->student(),
            'credential_type_id' => CredentialType::factory(),
            'college_id' => Department::factory()->college(),
            'student_number' => fake()->numerify('##-####-###'),
            'program' => 'BS '.fake()->words(2, true),
            'student_status' => fake()->randomElement(StudentStatus::cases()),
            'last_school_year' => $year.'-'.($year + 1),
            'purpose' => fake()->sentence(),
            'copies' => 1,
            'requires_payment' => false,
            'fee' => null,
            'status' => RequestStatus::RegistrarReview,
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

    public function paid(string $fee = '150.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_payment' => true,
            'fee' => $fee,
            'status' => RequestStatus::AwaitingPayment,
        ]);
    }
}
