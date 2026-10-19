<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('curriculum_courses')->select('id', 'course_code', 'course_name')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    foreach (['course_code', 'course_name'] as $field) {
                        if (! is_string($row->{$field}) || trim($row->{$field}) === '' || mb_strlen($row->{$field}) > 255) {
                            throw new RuntimeException("Cannot require snapshots for curriculum_courses ID {$row->id}: invalid {$field}.");
                        }
                    }
                }
            });

        Schema::table('curriculum_courses', function (Blueprint $table): void {
            $table->string('course_code', 255)->nullable(false)->change();
            $table->string('course_name', 255)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_courses', function (Blueprint $table): void {
            $table->string('course_code', 255)->nullable()->change();
            $table->string('course_name', 255)->nullable()->change();
        });
    }
};
