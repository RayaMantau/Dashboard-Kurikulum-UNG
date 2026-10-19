<?php

namespace Database\Factories;

use App\Models\Curriculum;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curriculum_id' => Curriculum::factory(),
            'semester_number' => fake()->numberBetween(1, 8),
            'name' => 'Semester '.$this->faker->numberBetween(1, 8),
            'total_sks' => fake()->numberBetween(18, 24),
        ];
    }
}
