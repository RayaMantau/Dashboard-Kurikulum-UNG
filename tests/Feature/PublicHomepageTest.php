<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumReview;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_homepage_has_public_shell_and_empty_database_is_safe(): void
    {
        $this->get(route('home'))->assertOk()->assertViewIs('public.home')
            ->assertViewHas('statistics', ['Fakultas Aktif' => 0, 'Program Studi Aktif' => 0, 'Kurikulum Aktif' => 0])
            ->assertSee('Dashboard Kurikulum')->assertSee('Universitas Negeri Gorontalo')
            ->assertSee('Belum ada fakultas aktif yang tersedia.')
            ->assertSee('Belum ada kurikulum aktif yang dipublikasikan.')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.asset('public.css').'"', false)
            ->assertDontSee('internal-sidebar')->assertDontSee('internal.css')->assertDontSee('Laravel');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('curriculum_reviews', 0);
    }

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [$role->value => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_internal_users_can_browse_public_home_with_shared_dashboard_entry(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'name' => 'PrivateAccountName']);
        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Dashboard Internal')
            ->assertSee('href="'.route('dashboard').'"', false)->assertDontSee('href="'.route('login').'"', false)
            ->assertDontSee('PrivateAccountName')->assertDontSee('/kaprodi')->assertDontSee('/admin/kurikulum')
            ->assertDontSee('internal-sidebar');
    }

    public function test_inactive_authenticated_user_can_still_read_public_page_without_gaining_internal_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => false]);
        $this->actingAs($user)->get(route('home'))->assertOk();
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_statistics_and_previews_exclude_nonpublic_statuses_and_inactive_parents(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'Fakultas Terbuka', 'is_active' => true]);
        $inactiveFaculty = Faculty::factory()->create(['name' => 'HiddenFacultyName', 'is_active' => false]);
        $program = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => true, 'name' => 'Program Terbuka']);
        $inactiveProgram = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false, 'name' => 'HiddenProgramName']);
        $hiddenParent = StudyProgram::factory()->create(['faculty_id' => $inactiveFaculty->id, 'is_active' => true, 'name' => 'HiddenParentProgram']);
        foreach (CurriculumStatus::cases() as $status) {
            Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status, 'name' => 'Version-'.$status->value]);
        }
        foreach ([$inactiveProgram, $hiddenParent] as $hidden) {
            Curriculum::factory()->create(['study_program_id' => $hidden->id, 'status' => CurriculumStatus::PUBLISHED, 'name' => 'HiddenPublished-'.$hidden->id]);
        }
        $response = $this->get(route('home', ['status' => 'draft', 'faculty_id' => $inactiveFaculty->id, 'study_program_id' => $inactiveProgram->id]))->assertOk()
            ->assertViewHas('statistics', ['Fakultas Aktif' => 1, 'Program Studi Aktif' => 1, 'Kurikulum Aktif' => 1])
            ->assertSee('Version-published')->assertSee('Fakultas Terbuka')->assertSee('Program Terbuka')
            ->assertDontSee('HiddenFacultyName')->assertDontSee('HiddenProgramName')->assertDontSee('HiddenParentProgram')->assertDontSee('HiddenPublished-');
        foreach (CurriculumStatus::cases() as $status) {
            if ($status !== CurriculumStatus::PUBLISHED) {
                $response->assertDontSee('Version-'.$status->value);
            }
        }
        $this->assertCount(1, $response->viewData('faculties'));
        $this->assertCount(1, $response->viewData('curricula'));
        $this->actingAs(User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]))->get(route('home'))
            ->assertViewHas('statistics', ['Fakultas Aktif' => 1, 'Program Studi Aktif' => 1, 'Kurikulum Aktif' => 1])
            ->assertDontSee('Version-draft')->assertDontSee('Version-approved');
    }

    public function test_parent_deactivation_immediately_removes_publication_from_public_home(): void
    {
        $program = StudyProgram::factory()->create();
        Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'name' => 'VisibleThenHidden']);
        $this->get(route('home'))->assertSee('VisibleThenHidden');
        $program->update(['is_active' => false]);
        $this->get(route('home'))->assertDontSee('VisibleThenHidden')->assertViewHas('statistics', ['Fakultas Aktif' => 1, 'Program Studi Aktif' => 0, 'Kurikulum Aktif' => 0]);
        $program->update(['is_active' => true]);
        $program->faculty->update(['is_active' => false]);
        $this->get(route('home'))->assertDontSee('VisibleThenHidden')->assertViewHas('statistics', ['Fakultas Aktif' => 0, 'Program Studi Aktif' => 0, 'Kurikulum Aktif' => 0]);
    }

    public function test_previews_are_limited_and_deterministically_ordered_without_limiting_counts(): void
    {
        $this->travelTo(now()->startOfSecond());
        $ids = [];
        foreach (range(1, 8) as $number) {
            $faculty = Faculty::factory()->create(['name' => 'Fakultas '.sprintf('%02d', $number)]);
            $program = StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
            $ids[] = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'published_at' => now(), 'name' => 'Versi '.$number])->id;
        }
        $response = $this->get(route('home'))->assertOk()->assertViewHas('statistics', ['Fakultas Aktif' => 8, 'Program Studi Aktif' => 8, 'Kurikulum Aktif' => 8]);
        $this->assertSame(['Fakultas 01', 'Fakultas 02', 'Fakultas 03', 'Fakultas 04', 'Fakultas 05', 'Fakultas 06'], $response->viewData('faculties')->pluck('name')->all());
        $this->assertSame(array_reverse(array_slice($ids, -3)), $response->viewData('curricula')->modelKeys());
        Curriculum::whereKey($ids[0])->update(['published_at' => now()->addMinute()]);
        $this->assertSame($ids[0], $this->get(route('home'))->viewData('curricula')->first()->id);
    }

    public function test_private_content_is_never_rendered_and_get_does_not_mutate_domain_data(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'description' => 'SECRET-METADATA']);
        $actor = User::factory()->create(['name' => 'SECRET-ACTOR']);
        AuditLog::create(['entity_type' => Curriculum::class, 'entity_id' => $curriculum->id, 'action' => 'curriculum.published', 'user_id' => $actor->id, 'new_values' => ['secret' => 'SECRET-AUDIT'], 'user_agent' => 'SECRET-AGENT', 'created_at' => now()]);
        CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $actor->id, 'status' => 'revision', 'notes' => 'SECRET-REVIEW', 'reviewed_at' => now()]);
        Document::factory()->create(['curriculum_id' => $curriculum->id, 'file_path' => 'private/SECRET-DOCUMENT.pdf', 'uploaded_by' => $actor->id]);
        Notification::create(['user_id' => $actor->id, 'type' => 'curriculum_published', 'title' => 'SECRET-NOTIFICATION', 'message' => 'SECRET-MESSAGE']);
        $tables = ['curricula', 'audit_logs', 'notifications', 'curriculum_reviews', 'documents'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('home'))->assertOk()->assertDontSee('SECRET-')->assertDontSee('/storage/')->assertDontSee('/internal/')->assertDontSee('/admin/kurikulum')->assertDontSee('/kaprodi');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(audit_logs|notifications|curriculum_reviews|documents)\b/i', $query['query']);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_navigation_has_no_broken_future_links_and_accessible_menu_markup(): void
    {
        $response = $this->get(route('home'))->assertOk()->assertSee('href="'.route('home').'"', false)
            ->assertSee('aria-controls="public-navigation"', false)->assertSee('aria-expanded="false"', false)
            ->assertSee('id="public-navigation"', false)->assertSee('aria-label="Navigasi publik"', false)
            ->assertSee('href="'.route('faculties.index').'"', false)->assertSee('id="ringkasan"', false)
            ->assertSee('href="'.route('search').'"', false)->assertSee('Cari Mata Kuliah')->assertDontSee('Segera');
        $response->assertSee('href="'.route('study-programs.index').'"', false);
        foreach (['/register'] as $path) {
            $response->assertDontSee('href="'.url($path).'"', false)->assertDontSee('href="'.$path.'"', false);
        }
        $response->assertDontSee('<form', false)->assertDontSee('type="search"', false)->assertDontSee('href="#"', false);
        $this->assertStringContainsString('header', $response->getContent());
        $response->assertSee('<main id="main"', false)->assertSee('<footer', false);
    }

    public function test_preview_text_is_escaped_and_queries_do_not_grow_with_records(): void
    {
        $program = StudyProgram::factory()->create(['name' => '<script>PROGRAM</script>']);
        $program->faculty->update(['name' => '<script>FACULTY</script>']);
        $first = Curriculum::factory()->create(['study_program_id' => $program->id, 'name' => '<script>CURRICULUM</script>', 'status' => CurriculumStatus::PUBLISHED]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('home'))->assertOk()->assertSee($first->name)->assertDontSee('<script>CURRICULUM</script>', false)
            ->assertDontSee('<script>FACULTY</script>', false)->assertDontSee('<script>PROGRAM</script>', false);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        Curriculum::factory()->count(10)->create(['status' => CurriculumStatus::PUBLISHED]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('home'))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
