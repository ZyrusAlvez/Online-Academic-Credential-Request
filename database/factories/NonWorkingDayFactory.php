<?php

namespace Database\Factories;

use App\Enums\NonWorkingDayKind;
use App\Models\NonWorkingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonWorkingDay>
 */
class NonWorkingDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('2026-01-01', '2027-12-31')->format('Y-m-d'),
            'name' => fake()->words(3, true),
            'kind' => NonWorkingDayKind::Holiday,
        ];
    }
}
