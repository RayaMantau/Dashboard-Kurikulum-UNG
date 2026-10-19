<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curriculum_id')
                ->constrained('curricula')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('semester_number');
            $table->string('name');
            $table->decimal('total_sks', 5, 2)->default(0);

            $table->timestamps();

            $table->unique([
                'curriculum_id',
                'semester_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
