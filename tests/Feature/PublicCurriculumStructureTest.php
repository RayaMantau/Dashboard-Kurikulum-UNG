<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCurriculumStructureTest extends TestCase
{
    use RefreshDatabase;

    private function attachment(Curriculum $curriculum, Semester $semester, string $code = 'IF101', string $name = 'Algoritma', array $fields = []): CurriculumCourse
    {
        $course = Course::factory()->create(['study_program_id' => $curriculum->study_program_id, 'code' => $code, 'name' => $name]);

        return CurriculumCourse::factory()->create([...$fields, 'curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
    }

    private function detailUrl(Curriculum $curriculum, ?Semester $semester = null): string
    {
        $parameters = [$curriculum->studyProgram->slug, $curriculum->id];
        if ($semester) {
            return route('study-programs.curricula.semesters.show', [...$parameters, $semester->id]);
        }

        return route('study-programs.curricula.show', $parameters);
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function publicStatuses(): array
    {
        return ['published' => [CurriculumStatus::PUBLISHED], 'archived' => [CurriculumStatus::ARCHIVED]];
    }

    #[DataProvider('publicStatuses')]
    public function test_structure_uses_snapshots_for_public_and_archived_versions(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status, 'total_sks' => 155.5]);
        $second = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2, 'name' => 'Semester Kedua', 'total_sks' => 20.5]);
        $first = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1, 'name' => 'Semester Pertama', 'total_sks' => 99]);
        $required = $this->attachment($curriculum, $first, fields: ['sks' => 2.5, 'course_type' => CourseType::REQUIRED, 'is_active' => false, 'prerequisite_notes' => '<script>PRASYARAT</script>']);
        $this->attachment($curriculum, $second, 'IF102', 'Pilihan Akademik', ['sks' => 3, 'course_type' => CourseType::ELECTIVE]);
        $required->course->update(['code' => 'IF999', 'name' => 'SECRET-MASTER-NAME']);
        $response = $this->get($this->detailUrl($curriculum))->assertOk()->assertSeeInOrder(['Semester Pertama', 'IF101', 'Algoritma', 'Semester Kedua', 'IF102'])
            ->assertSee('Wajib')->assertSee('Pilihan')->assertSee('155.50')->assertSee('99.00')->assertSee('20.50')->assertSee('2.50 SKS');
        $response->assertSee($status === CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif');
        foreach ([$this->detailUrl($curriculum), $this->detailUrl($curriculum, $first)] as $url) {
            $this->get($url)->assertOk()->assertSee('IF101')->assertSee('Algoritma')->assertDontSee('IF999')->assertDontSee('SECRET-MASTER-NAME')
                ->assertSee('<script>PRASYARAT</script>')->assertDontSee('<script>PRASYARAT</script>', false);
        }
        $this->get($this->detailUrl($curriculum, $second))->assertOk()->assertDontSee('Prasyarat:')->assertSee('Pilihan');
        $this->assertSame('155.50', $curriculum->fresh()->total_sks);
        $this->assertSame('99.00', $first->fresh()->total_sks);
    }

    public function test_empty_semesters_and_zero_semesters_remain_visible(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $this->get($this->detailUrl($curriculum))->assertOk()->assertSee('Belum ada struktur semester yang tersedia.');
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'name' => 'Empty Semester']);
        foreach ([$this->detailUrl($curriculum), $this->detailUrl($curriculum, $semester)] as $url) {
            $this->get($url)->assertOk()->assertSee('Empty Semester')->assertSee('Belum ada mata kuliah pada semester ini.');
        }
    }

    public function test_internal_users_can_read_public_semester_with_production_breadcrumbs(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $url = $this->detailUrl($curriculum, $semester);
        $this->get($this->detailUrl($curriculum))->assertSee('href="'.$url.'"', false);
        foreach (UserRole::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get($url)->assertOk()->assertSee('Arsip')->assertSee('aria-label="Breadcrumb"', false)
                ->assertSee('href="'.$this->detailUrl($curriculum).'"', false)
                ->assertSee('Semester 1 - '.$curriculum->name);
        }
    }

    public function test_private_status_child_routes_remain_404_for_guests_and_internal_users(): void
    {
        foreach ([CurriculumStatus::DRAFT, CurriculumStatus::SUBMITTED, CurriculumStatus::REVISION, CurriculumStatus::APPROVED] as $status) {
            $curriculum = Curriculum::factory()->create(['status' => $status]);
            $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'name' => 'SECRET-SEMESTER']);
            $this->attachment($curriculum, $semester, name: 'SECRET-COURSE');
            $this->app['auth']->forgetGuards();
            $url = $this->detailUrl($curriculum, $semester);
            $this->get($url)->assertNotFound()->assertDontSee('SECRET-');
            foreach (UserRole::cases() as $role) {
                $this->actingAs(User::factory()->create(['role' => $role, 'study_program_id' => $curriculum->study_program_id]));
                $this->get($url)->assertNotFound()->assertDontSee('SECRET-');
            }
        }
    }

    public function test_nested_route_ownership_and_inactive_parents_return_404(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $other = Semester::factory()->create();
        $foreignProgram = StudyProgram::factory()->create();
        $this->get(route('study-programs.curricula.semesters.show', [$curriculum->studyProgram->slug, $curriculum->id, $other->id]))->assertNotFound();
        $this->get(route('study-programs.curricula.semesters.show', [$foreignProgram->slug, $curriculum->id, $semester->id]))->assertNotFound();
        $this->get(route('study-programs.curricula.semesters.show', [$curriculum->studyProgram->slug, $curriculum->id, 999999]))->assertNotFound();
        $curriculum->studyProgram->update(['is_active' => false]);
        $this->get($this->detailUrl($curriculum, $semester))->assertNotFound();
        $curriculum->studyProgram->update(['is_active' => true]);
        $curriculum->studyProgram->faculty->update(['is_active' => false]);
        $this->get($this->detailUrl($curriculum, $semester))->assertNotFound();
    }

    public function test_attachment_order_is_snapshot_code_then_id_and_mismatched_curriculum_is_hidden(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->attachment($curriculum, $semester, 'ZZ', 'Last Course');
        $first = $this->attachment($curriculum, $semester, 'AA', 'First Course');
        $first->course->update(['code' => 'OLD-AA']);
        $this->attachment($curriculum, $semester, 'AA', 'Second Course');
        $foreign = Curriculum::factory()->create();
        $this->attachment($foreign, $semester, 'XX', 'SECRET-MISMATCH');
        foreach ([$this->detailUrl($curriculum), $this->detailUrl($curriculum, $semester)] as $url) {
            $this->get($url)->assertOk()->assertSeeInOrder(['First Course', 'Second Course', 'Last Course'])->assertDontSee('SECRET-MISMATCH');
        }
    }

    public function test_reads_preserve_domain_records_and_query_growth_is_constant(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $this->attachment($curriculum, $semester);
        $urls = [$this->detailUrl($curriculum), $this->detailUrl($curriculum, $semester)];
        $counts = [];
        foreach ($urls as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get($url)->assertOk();
            $counts[$url] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        foreach (range(2, 5) as $number) {
            $additional = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => $number]);
            $this->attachment($curriculum, $additional, 'IF'.$number, 'Extra '.$number);
            $this->attachment($curriculum, $semester, 'OWN'.$number, 'More '.$number);
        }
        $tables = ['curricula', 'semesters', 'curriculum_courses', 'courses', 'documents', 'audit_logs', 'notifications', 'curriculum_reviews'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        foreach ($urls as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get($url)->assertOk()->assertDontSee('/download')->assertDontSee('/storage/');
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertSame($counts[$url], count($queries));
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
                $this->assertDoesNotMatchRegularExpression('/\b(courses|audit_logs|notifications|curriculum_reviews|users)\b/i', $query['query']);
            }
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }
}
