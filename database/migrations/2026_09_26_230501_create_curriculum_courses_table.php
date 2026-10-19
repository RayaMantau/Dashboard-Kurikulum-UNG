<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curriculum_id')
                ->constrained('curricula')
                ->cascadeOnDelete();

            $table->foreignId('semester_id')
                ->constrained('semesters')
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->decimal('sks', 4, 2);

            $table->string('course_type')->default('wajib');

            $table->text('prerequisite_notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'curriculum_id',
                'course_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
    }
};
