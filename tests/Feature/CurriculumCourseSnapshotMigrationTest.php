<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CurriculumCourse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumCourseSnapshotMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function stageA(): Migration
    {
        return require database_path('migrations/2026_09_28_225725_add_course_snapshot_fields_to_curriculum_courses_table.php');
    }

    private function stageB(): Migration
    {
        return require database_path('migrations/2026_09_28_225726_require_course_snapshot_fields_on_curriculum_courses_table.php');
    }

    private function removeSnapshots(): void
    {
        $this->stageB()->down();
        $this->stageA()->down();
    }

    public function test_backfill_preserves_existing_rows_and_timestamps_and_is_safe_to_retry(): void
    {
        $course = Course::factory()->create(['code' => 'IF101', 'name' => 'Algoritma']);
        $attachment = CurriculumCourse::factory()->for($course)->create([
            'created_at' => '2020-01-02 03:04:05', 'updated_at' => '2021-02-03 04:05:06',
            'prerequisite_notes' => 'Lulus pengantar', 'is_active' => false,
        ]);
        $this->removeSnapshots();
        $before = (array) DB::table('curriculum_courses')->find($attachment->id);
        $this->stageA()->up();
        $this->stageB()->up();
        $after = (array) DB::table('curriculum_courses')->find($attachment->id);

        $this->assertSame([...$before, 'course_code' => 'IF101', 'course_name' => 'Algoritma'], $after);
        $course->update(['code' => 'IF201', 'name' => 'Master baru']);
        $this->stageA()->up();
        $this->assertSame($after, (array) DB::table('curriculum_courses')->find($attachment->id));
    }

    public static function snapshotColumns(): array
    {
        return [['course_code'], ['course_name']];
    }

    public function test_backfill_processes_multiple_chunks_without_losing_rows(): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $course = $attachment->course;
        $this->removeSnapshots();
        $template = (array) DB::table('curriculum_courses')->find($attachment->id);
        unset($template['id']);
        for ($index = 1; $index <= 501; $index++) {
            $courseId = DB::table('courses')->insertGetId([
                'study_program_id' => $course->study_program_id,
                'code' => 'CHUNK'.$index, 'name' => 'Mata kuliah '.$index,
            ]);
            DB::table('curriculum_courses')->insert([...$template, 'course_id' => $courseId]);
        }

        $this->stageA()->up();
        $this->stageB()->up();

        $this->assertDatabaseCount('curriculum_courses', 502);
        $this->assertSame(0, DB::table('curriculum_courses')->whereNull('course_code')->orWhereNull('course_name')->count());
        $this->assertDatabaseHas('curriculum_courses', ['course_code' => 'CHUNK501', 'course_name' => 'Mata kuliah 501']);
        $this->assertSame(502, DB::table('curriculum_courses')->where('created_at', $template['created_at'])->where('updated_at', $template['updated_at'])->count());
    }

    #[DataProvider('snapshotColumns')]
    public function test_final_schema_rejects_null_snapshots(string $column): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('curriculum_courses')->where('id', $attachment->id)->update([$column => null]);
    }

    public static function invalidSources(): array
    {
        return [['missing'], ['blank_code'], ['blank_name'], ['long_name']];
    }

    #[DataProvider('invalidSources')]
    public function test_invalid_source_fails_before_ddl_without_changing_existing_rows(string $invalid): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $this->removeSnapshots();
        if ($invalid === 'missing') {
            // Legacy corrupt fixture only; production migration never disables FK checks.
            DB::statement('PRAGMA defer_foreign_keys = ON');
            DB::table('curriculum_courses')->where('id', $attachment->id)->update(['course_id' => 999999]);
        } else {
            $field = $invalid === 'blank_code' ? 'code' : 'name';
            DB::table('courses')->where('id', $attachment->course_id)->update([$field => $invalid === 'long_name' ? str_repeat('x', 256) : '   ']);
        }
        $before = (array) DB::table('curriculum_courses')->find($attachment->id);
        try {
            $this->stageA()->up();
            $this->fail('Invalid source must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("curriculum_courses ID {$attachment->id}", $exception->getMessage());
            $this->assertFalse(Schema::hasColumn('curriculum_courses', 'course_code'));
            $this->assertSame($before, (array) DB::table('curriculum_courses')->find($attachment->id));
        }
    }

    public function test_stage_b_refuses_incomplete_backfill(): void
    {
        $attachment = CurriculumCourse::factory()->create();
        $this->stageB()->down();
        DB::table('curriculum_courses')->where('id', $attachment->id)->update(['course_name' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid course_name');
        $this->stageB()->up();
    }

    public function test_mysql_grammar_generates_nullable_add_and_nonnullable_change_without_empty_defaults(): void
    {
        $connection = new MySqlConnection(null, 'snapshot_test');
        $connection->useDefaultSchemaGrammar();
        $add = new Blueprint($connection, 'curriculum_courses');
        $add->string('course_code', 255)->nullable();
        $add->string('course_name', 255)->nullable();
        $sql = implode('; ', $add->toSql());
        $this->assertStringContainsString('varchar(255) null', $sql);
        $change = new Blueprint($connection, 'curriculum_courses');
        $change->string('course_code', 255)->nullable(false)->change();
        $change->string('course_name', 255)->nullable(false)->change();
        $sql = implode('; ', $change->toSql());
        $this->assertStringContainsString('modify', $sql);
        $this->assertStringContainsString('course_code', $sql);
        $this->assertStringContainsString('course_name', $sql);
        $this->assertStringContainsString('varchar(255) not null', $sql);
        $this->assertStringNotContainsString('default', $sql);
    }
}
