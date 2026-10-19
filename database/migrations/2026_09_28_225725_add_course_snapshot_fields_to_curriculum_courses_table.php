<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight before MySQL's implicitly committed DDL. Deploy with writes paused.
        $this->sourceRows()->chunkById(500, function (Collection $rows): void {
            foreach ($rows as $row) {
                $this->validateSource($row);
            }
        }, 'curriculum_courses.id', 'id');

        foreach (['course_code', 'course_name'] as $column) {
            if (! Schema::hasColumn('curriculum_courses', $column)) {
                Schema::table('curriculum_courses', function (Blueprint $table) use ($column): void {
                    $table->string($column, 255)->nullable();
                });
            }
        }

        $this->sourceRows()->addSelect('curriculum_courses.course_code', 'curriculum_courses.course_name')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $this->validateSource($row);
                    // Preserve completed snapshots on retry, and never touch timestamps.
                    DB::table('curriculum_courses')->where('id', $row->id)->update([
                        'course_code' => $row->course_code ?? $row->source_code,
                        'course_name' => $row->course_name ?? $row->source_name,
                    ]);
                }
            }, 'curriculum_courses.id', 'id');
    }

    private function sourceRows(): Builder
    {
        return DB::table('curriculum_courses')
            ->leftJoin('courses', 'courses.id', '=', 'curriculum_courses.course_id')
            ->select('curriculum_courses.id', 'courses.code as source_code', 'courses.name as source_name');
    }

    private function validateSource(object $row): void
    {
        foreach (['source_code', 'source_name'] as $field) {
            if (! is_string($row->{$field}) || trim($row->{$field}) === '' || mb_strlen($row->{$field}) > 255) {
                throw new RuntimeException("Cannot backfill curriculum_courses ID {$row->id}: missing Course or invalid {$field}.");
            }
        }
    }

    public function down(): void
    {
        Schema::table('curriculum_courses', function (Blueprint $table): void {
            $table->dropColumn(['course_code', 'course_name']);
        });
    }
};
