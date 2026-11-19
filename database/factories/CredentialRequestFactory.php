<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\RequestApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            // Its own application of one credential (spec 27); numbered after creation.
            'application_id' => fn (array $attributes) => RequestApplication::create([
                'reference_no' => 'tmp-'.Str::random(12),
                'user_id' => $attributes['user_id'],
                'submitted_at' => now(),
            ])->id,
            'item_no' => 1,
            // The owner's copy, as RequestWorkflow::submit() takes it (REQ-15-06).
            'requester_name' => fn (array $attributes) => User::find($attributes['user_id'])?->requesterName() ?: fake()->name(),
            'sex' => fn (array $attributes) => User::find($attributes['user_id'])?->sex,
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
            // Same format as a real submission (REQ-04-03, REQ-27-01).
            $application = $request->application()->firstOrFail();

            if (str_starts_with($application->reference_no, 'tmp-')) {
                $application->update(['reference_no' => CredentialRequest::trackingNumber($application->id, $request->created_at)]);
            }

            $request->forceFill(['reference_no' => "{$application->reference_no}-{$request->item_no}"])->save();
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
