<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\IdentityDocument;
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
            // The owner's copy, as RequestWorkflow::submit() takes it (REQ-15-06).
            'requester_name' => fn (array $attributes) => User::find($attributes['user_id'])?->requesterName() ?: fake()->name(),
            'contact_number' => fn (array $attributes) => User::find($attributes['user_id'])?->contact_number,
            'identity_status' => ReviewStatus::Pending,
            'credential_type_id' => CredentialType::factory(),
            'college_id' => Department::factory()->college(),
            'academic_status' => AcademicStatus::Graduate,
            'program' => 'BS '.fake()->words(2, true),
            'student_number' => fake()->numerify('##-####-###'),
            'attended_from' => 2019,
            'attended_to' => 2023,
            'purpose' => fake()->sentence(),
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

    /**
     * With a valid ID of its owner attached, in the given review status (spec 15).
     */
    public function withIdentity(ReviewStatus $status = ReviewStatus::Pending): static
    {
        return $this->state(['identity_status' => $status])
            ->afterCreating(function (CredentialRequest $request): void {
                $request->forceFill([
                    'identity_document_id' => IdentityDocument::factory()->create(['user_id' => $request->user_id])->id,
                ])->save();
            });
    }
}
