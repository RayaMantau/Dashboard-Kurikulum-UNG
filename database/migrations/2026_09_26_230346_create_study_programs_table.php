<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('faculty_id')
                ->constrained('faculties')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code');
            $table->string('degree');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('accreditation')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['faculty_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_programs');
    }
};
