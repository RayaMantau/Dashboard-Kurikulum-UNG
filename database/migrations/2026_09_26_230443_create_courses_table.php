<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_program_id')
                ->constrained('study_programs')
                ->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique([
                'study_program_id',
                'code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
