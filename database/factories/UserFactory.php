<?php

namespace Database\Factories;

use App\Enums\AcademicStatus;
use App\Enums\IdentityStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Student,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'contact_number' => null,
            'academic_status' => null,
            'college_id' => null,
            'program' => null,
            'student_number' => null,
            'attended_from' => null,
            'attended_to' => null,
            'identity_status' => IdentityStatus::Unsubmitted,
            'identity_verified_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Student,
        ]);
    }

    public function registrar(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Registrar,
        ]);
    }

    public function departmentStaff(?Department $department = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Department,
            'department_id' => $department ?? Department::factory(),
        ]);
    }

    /**
     * A requester with a complete academic profile and a verified identity (spec 01).
     */
    public function withProfile(?Department $college = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Student,
            'contact_number' => '0917'.fake()->numerify('#######'),
            'academic_status' => AcademicStatus::Graduate,
            'college_id' => $college ?? Department::factory()->college(),
            'program' => 'BS Computer Science',
            'student_number' => fake()->numerify('20-####-###'),
            'attended_from' => 2020,
            'attended_to' => 2024,
            'identity_status' => IdentityStatus::Verified,
            'identity_verified_at' => now(),
        ]);
    }
}
