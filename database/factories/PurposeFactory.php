<?php

namespace Database\Factories;

use App\Models\Purpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purpose>
 */
class PurposeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
