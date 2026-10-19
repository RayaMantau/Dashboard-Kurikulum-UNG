<?php

namespace Database\Factories;

use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumCourse>
 */
class CurriculumCourseFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (CurriculumCourse $attachment): void {
            $attachment->captureCourseIdentity();
        });
    }

    public function definition(): array
    {
        return [
            'curriculum_id' => Curriculum::factory(),
            'semester_id' => Semester::factory(),
            'course_id' => Course::factory(),
            'sks' => fake()->randomElement([2, 3, 4]),
            'course_type' => fake()->randomElement([
                CourseType::REQUIRED,
                CourseType::ELECTIVE,
            ]),
            'prerequisite_notes' => null,
            'is_active' => true,
        ];
    }
}
