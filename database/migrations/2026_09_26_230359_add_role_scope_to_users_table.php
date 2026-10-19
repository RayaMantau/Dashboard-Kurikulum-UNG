<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')
                ->default('study_program_admin')
                ->after('password');

            $table->foreignId('faculty_id')
                ->nullable()
                ->after('role')
                ->constrained('faculties')
                ->nullOnDelete();

            $table->foreignId('study_program_id')
                ->nullable()
                ->after('faculty_id')
                ->constrained('study_programs')
                ->nullOnDelete();

            $table->boolean('is_active')
                ->default(true)
                ->after('study_program_id');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['faculty_id']);
            $table->dropForeign(['study_program_id']);

            $table->dropColumn([
                'role',
                'faculty_id',
                'study_program_id',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
