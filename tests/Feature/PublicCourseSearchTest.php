<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\CurriculumReview;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCourseSearchTest extends TestCase
{
    use RefreshDatabase;

    private function occurrence(?Curriculum $curriculum = null, string $code = 'IF101', string $name = 'Algoritma Lama', ?Course $course = null, ?Semester $semester = null): CurriculumCourse
    {
        $curriculum ??= Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'year' => 2026]);
        $semester ??= Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $course ??= Course::factory()->create(['study_program_id' => $curriculum->study_program_id, 'code' => $code, 'name' => $name]);

        return CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id, 'sks' => 3, 'course_type' => CourseType::REQUIRED]);
    }

    public function test_initial_page_gives_guidance_without_querying_attachments(): void
    {
        $this->occurrence();
        foreach (['', '   '] as $keyword) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('search', ['q' => $keyword]))->assertOk()->assertViewHas('results', null)
                ->assertSee('Masukkan nama atau kode mata kuliah')->assertDontSee('IF101');
            foreach (DB::getQueryLog() as $query) {
                $this->assertStringNotContainsString('curriculum_courses', $query['query']);
            }
            DB::disableQueryLog();
        }
    }

    public function test_navigation_homepage_and_authenticated_access(): void
    {
        $this->get(route('search'))->assertOk()->assertSee('Cari Mata Kuliah | Dashboard Kurikulum UNG')
            ->assertSee('href="'.route('search').'"  aria-current="page"', false)
            ->assertSee('method="GET"', false)->assertSee('Reset Filter')->assertDontSee('Segera');
        $this->get(route('home'))->assertOk()->assertSee('href="'.route('search').'"', false)
            ->assertSee('Cari mata kuliah dalam kurikulum aktif dan arsip.')->assertDontSee('segera tersedia');
        foreach (UserRole::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('search'))->assertOk()->assertSee('Dashboard Internal');
        }
    }

    public function test_name_and_code_search_use_snapshot_after_master_changes(): void
    {
        $row = $this->occurrence();
        $row->course->update(['code' => 'IF999', 'name' => 'Algoritma Baru']);
        foreach (['  algoritma lama  ', 'if101'] as $keyword) {
            $response = $this->get(route('search', ['q' => $keyword]))->assertOk()->assertSee('IF101')->assertSee('Algoritma Lama')
                ->assertDontSee('IF999')->assertDontSee('Algoritma Baru')->assertSee('3.00 SKS')->assertSee('Wajib')
                ->assertSee($row->curriculum->name)->assertSee($row->curriculum->studyProgram->name)
                ->assertSee($row->curriculum->studyProgram->faculty->name)->assertSee('Kurikulum Aktif');
            $this->assertSame(1, $response->viewData('results')->total());
        }
        $this->get(route('search', ['q' => 'IF999']))->assertSee('Tidak ada mata kuliah yang sesuai dengan pencarian.');
        $url = route('study-programs.curricula.semesters.show', [$row->curriculum->studyProgram->slug, $row->curriculum_id, $row->semester_id]);
        $this->get(route('search', ['q' => 'IF101']))->assertSee('href="'.$url.'"', false);
        $this->get($url)->assertOk()->assertSee('IF101')->assertSee('Algoritma Lama');
    }

    public function test_public_status_allowlist_cannot_be_bypassed_by_login_or_forged_filter(): void
    {
        foreach (CurriculumStatus::cases() as $status) {
            $curriculum = Curriculum::factory()->create(['status' => $status, 'name' => 'VERSION-'.$status->value]);
            $this->occurrence($curriculum, 'CODE-'.$status->value, 'Matching Course');
        }
        foreach ([null, ...UserRole::cases()] as $role) {
            if ($role !== null) {
                $this->actingAs(User::factory()->create(['role' => $role]));
            }
            $response = $this->get(route('search', ['q' => 'Matching', 'status' => 'draft']))->assertOk()
                ->assertSee('CODE-published')->assertSee('CODE-archived')->assertSee('Kurikulum Aktif')->assertSee('Arsip');
            $this->assertSame(2, $response->viewData('results')->total());
            foreach (['draft', 'submitted', 'revision', 'approved'] as $private) {
                $response->assertDontSee('CODE-'.$private)->assertDontSee('VERSION-'.$private);
            }
        }
    }

    public function test_inactive_parents_are_excluded_from_results_and_options(): void
    {
        foreach (['program', 'faculty'] as $parent) {
            $row = $this->occurrence(code: 'HIDDEN-'.$parent);
            $program = $row->curriculum->studyProgram;
            if ($parent === 'program') {
                $program->update(['is_active' => false]);
            } else {
                $program->faculty->update(['is_active' => false]);
            }
            $response = $this->get(route('search', ['q' => 'Algoritma']))->assertOk()->assertDontSee('HIDDEN-'.$parent);
            $this->assertFalse($response->viewData('studyPrograms')->contains('id', $program->id));
            $this->get(route('search', ['study_program' => $program->slug]))->assertOk()->assertSee('Tidak ada mata kuliah yang sesuai');
            if ($parent === 'faculty') {
                $this->assertFalse($response->viewData('faculties')->contains('slug', $program->faculty->slug));
                $this->get(route('search', ['faculty' => $program->faculty->slug]))->assertOk()->assertSee('Tidak ada mata kuliah yang sesuai');
            }
        }
    }

    public function test_faculty_program_year_and_combined_filters_use_and_semantics(): void
    {
        $first = $this->occurrence(code: 'MATCH-A');
        $program = $first->curriculum->studyProgram;
        $otherProgram = StudyProgram::factory()->create(['faculty_id' => $program->faculty_id]);
        $second = $this->occurrence(Curriculum::factory()->create(['study_program_id' => $otherProgram->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2025]), 'MATCH-B');
        $third = $this->occurrence(code: 'MATCH-C');
        $cases = [
            [['faculty' => $program->faculty->slug], [$first->id, $second->id]],
            [['study_program' => $program->slug], [$first->id]],
            [['year' => '2025'], [$second->id]],
            [['faculty' => $program->faculty->slug, 'study_program' => $third->curriculum->studyProgram->slug], []],
            [['q' => 'MATCH', 'faculty' => $program->faculty->slug, 'study_program' => $program->slug, 'year' => '2026'], [$first->id]],
            [['q' => 'MATCH-B', 'faculty' => $program->faculty->slug, 'study_program' => $program->slug, 'year' => '2026'], []],
        ];
        foreach ($cases as [$filters, $ids]) {
            $response = $this->get(route('search', $filters))->assertOk();
            $this->assertEqualsCanonicalizing($ids, $response->viewData('results')->pluck('id')->all());
        }
        $response = $this->get(route('search', ['faculty' => $program->faculty->slug]));
        $this->assertEqualsCanonicalizing([$program->id, $otherProgram->id], $response->viewData('studyPrograms')->pluck('id')->all());
    }

    public function test_duplicate_course_occurrences_are_retained_and_public_labels_are_used(): void
    {
        $first = $this->occurrence();
        $archived = Curriculum::factory()->create(['study_program_id' => $first->curriculum->study_program_id, 'status' => CurriculumStatus::ARCHIVED, 'year' => 2027]);
        $second = $this->occurrence($archived, course: $first->course);
        $second->update(['course_type' => CourseType::ELECTIVE, 'is_active' => false]);
        $response = $this->get(route('search', ['q' => 'IF101']))->assertOk()->assertSee('Kurikulum Aktif')->assertSee('Arsip')->assertSee('Pilihan');
        $this->assertSame([$first->id, $second->id], $response->viewData('results')->pluck('id')->all());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidFilters(): array
    {
        return [
            'unknown faculty' => [['faculty' => 'missing']], 'unknown program' => [['study_program' => 'missing']],
            'bad year' => [['year' => 'abc']], 'negative year' => [['year' => '-1']], 'overflow year' => [['year' => '65536']],
            'array keyword' => [['q' => ['Algoritma']]], 'array faculty' => [['faculty' => ['anything']]],
            'array program' => [['study_program' => ['anything']]], 'array year' => [['year' => ['2026']]],
            'long keyword' => [['q' => str_repeat('a', 256)]], 'injection' => [['q' => "' OR 1=1 --"]],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_fail_closed_without_errors(array $filters): void
    {
        $this->occurrence();
        $response = $this->get(route('search', $filters))->assertOk()->assertSee('Tidak ada mata kuliah yang sesuai dengan pencarian.');
        $this->assertSame(0, $response->viewData('results')->total());
    }

    public function test_year_options_only_include_public_curricula_with_active_parents(): void
    {
        $this->occurrence(Curriculum::factory()->create(['year' => 0, 'status' => CurriculumStatus::PUBLISHED]));
        $this->occurrence(Curriculum::factory()->create(['year' => 65535, 'status' => CurriculumStatus::ARCHIVED]));
        Curriculum::factory()->create(['year' => 2021, 'status' => CurriculumStatus::DRAFT]);
        $hidden = Curriculum::factory()->create(['year' => 2022, 'status' => CurriculumStatus::PUBLISHED]);
        $hidden->studyProgram->update(['is_active' => false]);
        $response = $this->get(route('search'))->assertOk();
        $this->assertSame([65535, 0], $response->viewData('years')->all());
        foreach (['0', '65535'] as $year) {
            $this->assertSame(1, $this->get(route('search', ['year' => $year]))->assertOk()->viewData('results')->total());
        }
    }

    public function test_pagination_preserves_all_filters_and_orders_by_snapshot_code(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'year' => 2026]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        for ($number = 16; $number >= 1; $number--) {
            $this->occurrence($curriculum, sprintf('IF%03d', $number), semester: $semester);
        }
        $filters = ['q' => 'Algoritma', 'faculty' => $curriculum->studyProgram->faculty->slug, 'study_program' => $curriculum->studyProgram->slug, 'year' => '2026'];
        $response = $this->get(route('search', $filters))->assertOk()->assertSee('IF001')->assertDontSee('IF016');
        $results = $response->viewData('results');
        $this->assertSame(16, $results->total());
        $this->assertSame(15, $results->count());
        parse_str(parse_url($results->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame([...$filters, 'page' => '2'], $query);
        $this->get($results->nextPageUrl())->assertOk()->assertSee('IF016')->assertDontSee('IF001');
    }

    public function test_mismatched_semester_is_excluded(): void
    {
        $other = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $semester = Semester::factory()->create(['curriculum_id' => $other->id]);
        $this->occurrence(semester: $semester);
        $this->get(route('search', ['q' => 'IF101']))->assertOk()->assertSee('Tidak ada mata kuliah yang sesuai');
    }

    public function test_keyword_and_stored_result_text_are_escaped(): void
    {
        $text = '<script>alert(1)</script>';
        $row = $this->occurrence(name: $text);
        $row->curriculum->update(['name' => '<img src=x onerror=alert(2)>']);
        $response = $this->get(route('search', ['q' => $text]))->assertOk()->assertSee($text)->assertDontSee($text, false)
            ->assertSee('<img src=x onerror=alert(2)>')->assertDontSee('<img src=x onerror=alert(2)>', false);
        $this->assertSame(1, $response->viewData('results')->total());
    }

    public function test_like_wildcards_remain_scoped_to_public_results(): void
    {
        $this->occurrence();
        $this->occurrence(Curriculum::factory()->create(['status' => CurriculumStatus::DRAFT]), 'SECRET');
        foreach (['%', '_'] as $keyword) {
            $response = $this->get(route('search', ['q' => $keyword]))->assertOk()->assertSee('IF101')->assertDontSee('SECRET');
            $this->assertSame(1, $response->viewData('results')->total());
        }
    }

    public function test_search_is_read_only_and_query_count_does_not_grow_per_result(): void
    {
        $row = $this->occurrence();
        $actor = User::factory()->create(['name' => 'SECRET-ACTOR', 'email' => 'SECRET-EMAIL@example.test']);
        $row->curriculum->update(['created_by' => $actor->id, 'updated_by' => $actor->id, 'description' => 'SECRET-DESCRIPTION']);
        AuditLog::create(['entity_type' => Curriculum::class, 'entity_id' => $row->curriculum_id, 'action' => 'curriculum.published', 'user_id' => $actor->id, 'new_values' => ['secret' => 'SECRET-AUDIT'], 'created_at' => now()]);
        CurriculumReview::create(['curriculum_id' => $row->curriculum_id, 'reviewer_id' => $actor->id, 'status' => 'revision', 'notes' => 'SECRET-REVIEW', 'reviewed_at' => now()]);
        Document::factory()->create(['curriculum_id' => $row->curriculum_id, 'file_path' => 'private/SECRET-DOCUMENT.pdf', 'uploaded_by' => $actor->id]);
        Notification::create(['user_id' => $actor->id, 'type' => 'curriculum_published', 'title' => 'SECRET-NOTIFICATION', 'message' => 'SECRET-MESSAGE']);
        $tables = ['curriculum_courses', 'courses', 'curricula', 'semesters', 'audit_logs', 'notifications', 'curriculum_reviews', 'documents'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('search', ['q' => 'Algoritma']))->assertOk()->assertDontSee('SECRET-')->assertDontSee('/storage/');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(insert|update|delete)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(from|join) ["`]?('.'courses|users|documents|audit_logs|notifications|curriculum_reviews'.')["`]?\b/i', $query['query']);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
        for ($number = 0; $number < 5; $number++) {
            $this->occurrence();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('search', ['q' => 'Algoritma']))->assertOk();
        $this->assertCount(count($queries), DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_ordering_is_stable_across_year_faculty_program_semester_and_id(): void
    {
        $program = StudyProgram::factory()->create(['name' => 'AAA Program']);
        $program->faculty->update(['name' => 'AAA Faculty']);
        $otherProgram = StudyProgram::factory()->create(['faculty_id' => $program->faculty_id, 'name' => 'ZZZ Program']);
        $otherFacultyProgram = StudyProgram::factory()->create(['name' => 'AAA Program']);
        $otherFacultyProgram->faculty->update(['name' => 'ZZZ Faculty']);
        $lateFaculty = $this->occurrence(Curriculum::factory()->create(['study_program_id' => $otherFacultyProgram->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2026]));
        $lateProgram = $this->occurrence(Curriculum::factory()->create(['study_program_id' => $otherProgram->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2026]));
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2026]);
        $secondSemester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        $lateSemester = $this->occurrence($curriculum, code: 'AA001', semester: $secondSemester);
        $first = $this->occurrence($curriculum, code: 'ZZ001');
        $tie = $this->occurrence(Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2026]), course: $first->course);
        $newer = $this->occurrence(Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'year' => 2027]));
        $archive = $this->occurrence(Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED, 'year' => 2028]));
        $expected = [$newer->id, $first->id, $tie->id, $lateSemester->id, $lateProgram->id, $lateFaculty->id, $archive->id];
        $this->assertSame($expected, $this->get(route('search', ['q' => 'Algoritma']))->assertOk()->viewData('results')->pluck('id')->all());
    }
}
