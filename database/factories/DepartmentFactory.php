<?php

namespace Database\Factories;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Office of '.fake()->unique()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'type' => DepartmentType::Office,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function college(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'College of '.fake()->unique()->words(2, true),
            'type' => DepartmentType::College,
            'description' => "Academic verification and Dean's approval",
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function registrarOffice(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Office of the Registrar',
            'code' => Department::REGISTRAR_CODE,
            'type' => DepartmentType::Office,
        ]);
    }
}
