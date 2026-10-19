<?php

namespace Tests\Feature;

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

class PublicCurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_list_separates_current_and_archived_and_hides_internal_records(): void
    {
        $program = StudyProgram::factory()->create();
        foreach (CurriculumStatus::cases() as $status) {
            Curriculum::factory()->create(['study_program_id' => $program->id, 'name' => 'VERSION-'.$status->value, 'status' => $status]);
        }
        $other = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED, 'name' => 'OTHER-PROGRAM']);
        $response = $this->get(route('study-programs.curricula.index', $program->slug))->assertOk()
            ->assertSeeInOrder(['id="current-title"', 'VERSION-published', 'id="history-title"', 'VERSION-archived'], false)
            ->assertSee('Arsip')->assertDontSee($other->name);
        foreach (['draft', 'submitted', 'revision', 'approved'] as $status) {
            $response->assertDontSee('VERSION-'.$status);
        }
        $this->assertSame(CurriculumStatus::PUBLISHED, $response->viewData('current')->status);
        $this->assertSame(1, $response->viewData('archives')->total());
    }

    /** @return array<string, array{CurriculumStatus, bool}> */
    public static function statuses(): array
    {
        return collect(CurriculumStatus::cases())->mapWithKeys(fn (CurriculumStatus $status): array => [
            $status->value => [$status, in_array($status, [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED], true)],
        ])->all();
    }

    #[DataProvider('statuses')]
    public function test_detail_status_allowlist_applies_to_guests_and_every_internal_role(CurriculumStatus $status, bool $public): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status, 'name' => 'SelectedVersion', 'description' => 'Public profile', 'year' => 2022, 'effective_year' => 2023, 'total_sks' => 144, 'total_semesters' => 8]);
        $program = $curriculum->studyProgram;
        $url = route('study-programs.curricula.show', [$program->slug, $curriculum->id]);
        $actors = [null];
        foreach (UserRole::cases() as $role) {
            $actors[] = User::factory()->create(['role' => $role, 'study_program_id' => $program->id, 'faculty_id' => $program->faculty_id]);
        }
        foreach ($actors as $actor) {
            if ($actor) {
                $this->actingAs($actor);
            }
            $response = $this->get($url)->assertStatus($public ? 200 : 404);
            if ($public) {
                $response->assertSee('SelectedVersion')->assertSee('Public profile')->assertSee('144.00')->assertSee('2023')
                    ->assertSee($status === CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif')
                    ->assertSee($program->name)->assertSee($program->faculty->name);
                $this->get(route('study-programs.curricula.index', $program->slug))->assertOk();
            } else {
                $response->assertDontSee('SelectedVersion')->assertDontSee('Public profile');
            }
        }
    }

    public function test_nested_cross_program_and_unknown_ids_return_404(): void
    {
        $program = StudyProgram::factory()->create();
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'name' => 'HiddenForeignVersion']);
        foreach ([$curriculum->id, 999999] as $id) {
            $this->get(route('study-programs.curricula.show', [$program->slug, $id]))->assertNotFound()->assertDontSee('HiddenForeignVersion');
        }
        $this->get(route('study-programs.curricula.index', 'unknown-program'))->assertNotFound();
    }

    public function test_inactive_program_or_faculty_blocks_both_routes_even_for_public_statuses(): void
    {
        foreach ([CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED] as $status) {
            $curriculum = Curriculum::factory()->create(['status' => $status, 'name' => 'HiddenVersion']);
            $program = $curriculum->studyProgram;
            foreach (['program', 'faculty'] as $parent) {
                $program->update(['is_active' => $parent !== 'program']);
                $program->faculty->update(['is_active' => $parent !== 'faculty']);
                foreach ([route('study-programs.curricula.index', $program->slug), route('study-programs.curricula.show', [$program->slug, $curriculum->id])] as $url) {
                    $this->get($url)->assertNotFound()->assertDontSee('HiddenVersion');
                }
            }
        }
    }

    public function test_archived_only_is_public_and_prodi_links_to_history_without_claiming_current(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED]);
        $program = $curriculum->studyProgram;
        $url = route('study-programs.curricula.index', $program->slug);
        $this->get($url)->assertOk()->assertSee('Belum ada kurikulum aktif yang dipublikasikan.')
            ->assertSee($curriculum->name)->assertSee('Arsip')->assertDontSee('Belum ada kurikulum publik yang tersedia.');
        $this->get(route('study-programs.show', $program->slug))->assertOk()->assertSee('href="'.$url.'"', false)
            ->assertSee('Belum ada kurikulum aktif yang dipublikasikan.');
    }

    public function test_no_public_versions_has_neutral_empty_state_and_no_discovery_link(): void
    {
        $program = StudyProgram::factory()->create();
        foreach ([CurriculumStatus::DRAFT, CurriculumStatus::SUBMITTED, CurriculumStatus::REVISION, CurriculumStatus::APPROVED] as $status) {
            Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status, 'name' => 'SECRET-'.$status->value]);
        }
        $url = route('study-programs.curricula.index', $program->slug);
        $this->get($url)->assertOk()->assertSee('Belum ada kurikulum publik yang tersedia.')->assertDontSee('SECRET-');
        $this->get(route('study-programs.show', $program->slug))->assertOk()->assertDontSee('href="'.$url.'"', false);
    }

    public function test_duplicate_published_selection_and_archived_pagination_are_deterministic(): void
    {
        $program = StudyProgram::factory()->create();
        $base = ['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED];
        Curriculum::factory()->create([...$base, 'published_at' => '2020-01-01', 'year' => 2050, 'name' => 'OLD-PUBLICATION']);
        Curriculum::factory()->create([...$base, 'published_at' => '2021-01-01', 'name' => 'EARLIER-ID']);
        $current = Curriculum::factory()->create([...$base, 'published_at' => '2021-01-01', 'name' => 'CURRENT']);
        $ids = [];
        foreach (range(1, 13) as $number) {
            $ids[] = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::ARCHIVED,
                'archived_at' => '2020-01-01', 'year' => 2020])->id;
        }
        $newest = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::ARCHIVED, 'archived_at' => '2022-01-01', 'year' => 1990]);
        $yearTie = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::ARCHIVED, 'archived_at' => '2020-01-01', 'year' => 2021]);
        $expected = [$newest->id, $yearTie->id, ...array_reverse($ids)];
        $response = $this->get(route('study-programs.curricula.index', $program->slug))->assertOk()
            ->assertDontSee('OLD-PUBLICATION')->assertDontSee('EARLIER-ID');
        $this->assertSame($current->id, $response->viewData('current')->id);
        $archives = $response->viewData('archives');
        $this->assertSame(array_slice($expected, 0, 12), $archives->getCollection()->modelKeys());
        $second = $this->get($archives->nextPageUrl())->assertOk()->assertSee('CURRENT');
        $this->assertSame(array_slice($expected, 12), $second->viewData('archives')->getCollection()->modelKeys());
        $this->assertSame(3, $program->curricula()->where('status', CurriculumStatus::PUBLISHED)->count());
    }

    public function test_description_escaping_and_published_only_empty_history(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'description' => '<script>PAYLOAD</script>', 'effective_year' => null]);
        $program = $curriculum->studyProgram;
        $this->get(route('study-programs.curricula.show', [$program->slug, $curriculum->id]))->assertOk()
            ->assertSee($curriculum->description)->assertDontSee($curriculum->description, false)->assertDontSee('Tahun berlaku')
            ->assertSee('aria-label="Breadcrumb"', false);
        $this->get(route('study-programs.curricula.index', $program->slug))->assertOk()->assertSee($curriculum->name)
            ->assertSee('Belum ada riwayat kurikulum pada halaman ini.');
        $this->get(route('study-programs.show', $program->slug))->assertSee('href="'.route('study-programs.curricula.index', $program->slug).'"', false);
    }

    public function test_private_data_and_children_are_not_read_or_mutated(): void
    {
        $actor = User::factory()->create(['name' => 'SECRET-ACTOR', 'email' => 'secret@example.test']);
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'created_by' => $actor->id,
            'submitted_at' => '1999-01-02 03:04:05', 'approved_at' => '1999-02-03 04:05:06']);
        $program = $curriculum->studyProgram;
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $course = Course::factory()->create(['study_program_id' => $program->id, 'name' => 'Public snapshot course']);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $course->update(['name' => 'SECRET-MASTER-COURSE']);
        Document::factory()->create(['curriculum_id' => $curriculum->id, 'file_path' => 'private/SECRET-PATH.pdf']);
        CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $actor->id, 'status' => 'approved', 'notes' => 'SECRET-REVIEW', 'reviewed_at' => now()]);
        AuditLog::create(['user_id' => $actor->id, 'entity_type' => Curriculum::class, 'entity_id' => $curriculum->id, 'action' => 'curriculum.published', 'new_values' => ['secret' => 'SECRET-AUDIT'], 'created_at' => now()]);
        Notification::create(['user_id' => $actor->id, 'type' => 'test', 'title' => 'SECRET-NOTIFICATION', 'message' => 'SECRET-MESSAGE']);
        $tables = ['curricula', 'curriculum_reviews', 'audit_logs', 'notifications', 'documents', 'semesters', 'curriculum_courses'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ([route('study-programs.curricula.index', $program->slug), route('study-programs.curricula.show', [$program->slug, $curriculum->id])] as $url) {
            $this->get($url)->assertOk()->assertDontSee('SECRET-')->assertDontSee('secret@example.test')
                ->assertDontSee('1999-01-02')->assertDontSee('1999-02-03')->assertDontSee('submitted_at')->assertDontSee('approved_at')
                ->assertDontSee('/storage/')->assertDontSee('/internal/');
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(curriculum_reviews|audit_logs|notifications|courses|users)\b/i', $query['query']);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }
}
