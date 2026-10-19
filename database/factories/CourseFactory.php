<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'study_program_id' => StudyProgram::factory(),
            'code' => strtoupper(fake()->unique()->bothify('MK###')),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }
}
