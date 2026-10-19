<?php

namespace Database\Factories;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyProgram>
 */
class StudyProgramFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'faculty_id' => Faculty::factory(),
            'name' => $name,
            'code' => strtoupper(fake()->unique()->lexify('PS??')),
            'degree' => 'S1',
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'accreditation' => 'Baik Sekali',
            'website' => fake()->url(),
            'is_active' => true,
        ];
    }
}
