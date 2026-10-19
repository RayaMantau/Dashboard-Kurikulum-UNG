<?php

namespace Database\Factories;

use App\Models\Curriculum;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'curriculum_id' => Curriculum::factory(),
            'title' => fake()->sentence(3),
            'file_name' => fake()->word().'.pdf',
            'file_path' => 'curricula/'.fake()->uuid().'.pdf',
            'file_type' => 'pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'uploaded_by' => User::factory(),
        ];
    }
}
