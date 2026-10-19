<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_program_id')
                ->constrained('study_programs')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('effective_year')->nullable();

            $table->decimal('total_sks', 5, 2)->default(0);
            $table->unsignedTinyInteger('total_semesters')->default(0);

            $table->text('description')->nullable();

            $table->string('status')->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
