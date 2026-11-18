<?php

namespace Database\Factories;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curriculum>
 */
class CurriculumFactory extends Factory
{
    public function withRatification(): static
    {
        return $this->state(['ratified_at' => '2026-01-01', 'external_document_url' => 'https://example.test/kurikulum.pdf'])
            ->has(Document::factory()->state(['document_type' => Document::RATIFICATION_DECREE]), 'documents');
    }

    public function definition(): array
    {
        return [
            'study_program_id' => StudyProgram::factory(),
            'name' => 'Kurikulum '.$this->faker->year(),
            'year' => $this->faker->numberBetween(2020, 2026),
            'effective_year' => $this->faker->numberBetween(2020, 2026),
            'total_sks' => 144,
            'total_semesters' => 8,
            'description' => fake()->sentence(),
            'status' => CurriculumStatus::DRAFT,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
