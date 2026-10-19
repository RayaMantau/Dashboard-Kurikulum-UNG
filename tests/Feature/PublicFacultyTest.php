<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicFacultyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_active_directory_and_active_program_counts(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'Fakultas Terbuka', 'description' => 'Deskripsi fakultas']);
        $hidden = Faculty::factory()->create(['name' => 'SecretFaculty', 'is_active' => false]);
        StudyProgram::factory()->count(2)->create(['faculty_id' => $faculty->id]);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false]);
        StudyProgram::factory()->create(['faculty_id' => $hidden->id, 'name' => 'SecretParentProgram']);
        $response = $this->get(route('faculties.index', ['is_active' => 0, 'faculty_id' => $hidden->id]))
            ->assertOk()->assertViewIs('public.faculties.index')->assertSee($faculty->name)
            ->assertSee($faculty->code)->assertSee('Deskripsi fakultas')->assertSee('2 Program Studi aktif')
            ->assertDontSee('SecretFaculty')->assertDontSee('SecretParentProgram')
            ->assertSee('href="'.route('faculties.show', $faculty->slug).'"', false);
        $this->assertSame(1, $response->viewData('faculties')->total());
        $this->assertSame(2, $response->viewData('faculties')->first()->study_programs_count);
    }

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [$role->value => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_internal_roles_can_read_public_pages_without_management_permissions(UserRole $role): void
    {
        $faculty = Faculty::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role, 'name' => 'PrivateIdentity']));
        foreach ([route('faculties.index'), route('faculties.show', $faculty->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee('Dashboard Internal')->assertDontSee('PrivateIdentity')
                ->assertDontSee('/university-admin/')->assertDontSee('/admin/kurikulum');
        }
    }

    public function test_detail_contains_only_active_programs_of_selected_faculty_in_name_order(): void
    {
        $faculty = Faculty::factory()->create(['description' => 'Profil fakultas']);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Zeta Prodi']);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Alpha Prodi', 'degree' => 'S1', 'accreditation' => 'Unggul']);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false, 'name' => 'SecretInactiveProgram']);
        StudyProgram::factory()->create(['name' => 'SecretOtherFacultyProgram']);
        $response = $this->get(route('faculties.show', $faculty->slug))->assertOk()
            ->assertSee($faculty->name)->assertSee($faculty->code)->assertSee('Profil fakultas')
            ->assertSeeInOrder(['Alpha Prodi', 'Zeta Prodi'])->assertSee('Jenjang: S1')->assertSee('Akreditasi: Unggul')
            ->assertSee('2 Program Studi aktif')->assertDontSee('SecretInactiveProgram')->assertDontSee('SecretOtherFacultyProgram')
            ->assertSee('href="'.route('study-programs.index').'"', false)->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<title>'.$faculty->name.' | Dashboard Kurikulum UNG</title>', false);
        $this->assertSame(['Alpha Prodi', 'Zeta Prodi'], $response->viewData('studyPrograms')->pluck('name')->all());
    }

    public function test_inactive_unknown_and_numeric_identifier_cannot_open_detail(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'SecretInactive', 'description' => 'SecretDescription', 'is_active' => false]);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'SecretChild']);
        foreach ([$faculty->slug, 'unknown-faculty', (string) $faculty->id] as $key) {
            $this->get(route('faculties.show', $key))->assertNotFound()
                ->assertDontSee('SecretInactive')->assertDontSee('SecretDescription')->assertDontSee('SecretChild');
        }
    }

    public function test_empty_directory_and_faculty_without_active_program_are_safe(): void
    {
        $this->get(route('faculties.index'))->assertOk()->assertSee('Belum ada fakultas yang tersedia.');
        $faculty = Faculty::factory()->create(['description' => null]);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false]);
        $this->get(route('faculties.show', $faculty->slug))->assertOk()
            ->assertSee('Belum ada Program Studi aktif pada fakultas ini.')->assertSee('0 Program Studi aktif');
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'degree' => '', 'accreditation' => null]);
        $this->get(route('faculties.show', $faculty->slug))->assertOk()->assertDontSee('Jenjang:')->assertDontSee('Akreditasi:');
    }

    public function test_directory_paginates_twelve_in_name_then_id_order(): void
    {
        $ids = [];
        foreach (range(1, 13) as $number) {
            $ids[] = Faculty::factory()->create(['name' => 'Same Faculty'])->id;
        }
        $first = Faculty::factory()->create(['name' => 'A First Faculty']);
        Faculty::factory()->create(['name' => 'A Hidden Faculty', 'is_active' => false]);
        $response = $this->get(route('faculties.index'))->assertOk()->assertSee('rel="next"', false);
        $paginator = $response->viewData('faculties');
        $this->assertSame(14, $paginator->total());
        $this->assertSame(12, $paginator->perPage());
        $this->assertSame([$first->id, ...array_slice($ids, 0, 11)], $paginator->getCollection()->modelKeys());
        $second = $this->get($paginator->nextPageUrl())->assertOk()->assertSee('rel="prev"', false);
        $this->assertSame(array_slice($ids, 11), $second->viewData('faculties')->getCollection()->modelKeys());
    }

    public function test_description_and_names_are_escaped_in_directory_and_detail(): void
    {
        $faculty = Faculty::factory()->create(['name' => '<script>faculty</script>', 'description' => '<img src=x onerror=alert(1)>']);
        StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => '<script>program</script>']);
        foreach ([route('faculties.index'), route('faculties.show', $faculty->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee($faculty->name)->assertSee($faculty->description)
                ->assertDontSee($faculty->name, false)->assertDontSee($faculty->description, false)
                ->assertDontSee('<script>program</script>', false);
        }
    }

    public function test_navigation_and_homepage_links_use_slugs_and_preserve_future_boundaries(): void
    {
        $faculty = Faculty::factory()->create();
        $hidden = Faculty::factory()->create(['is_active' => false]);
        $response = $this->get(route('home'))->assertOk()->assertSee('Jelajahi Fakultas')
            ->assertSee('href="'.route('faculties.index').'"', false)
            ->assertSee('href="'.route('faculties.show', $faculty->slug).'"', false)
            ->assertDontSee('href="'.route('faculties.show', $hidden->slug).'"', false)
            ->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('study-programs.index').'"', false);
        $response->assertSee('href="'.route('search').'"', false)->assertDontSee('Segera');
        $this->get(route('faculties.show', $faculty->slug))->assertOk()
            ->assertSee('href="'.route('faculties.index').'"  aria-current="page"', false);
    }

    public function test_faculty_pages_are_read_only_and_never_query_or_expose_workflow_data(): void
    {
        $program = StudyProgram::factory()->create();
        foreach (CurriculumStatus::cases() as $status) {
            Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status, 'name' => 'SECRET-'.$status->value]);
        }
        $tables = ['faculties', 'study_programs', 'curricula', 'audit_logs', 'notifications', 'curriculum_reviews'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $urls = [route('faculties.index'), route('faculties.show', $program->faculty->slug)];
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($urls as $url) {
            $this->get($url)->assertOk()->assertDontSee('SECRET-')->assertDontSee('/storage/')
                ->assertDontSee('/internal/')->assertDontSee('/university-admin/')->assertDontSee('/kaprodi/');
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(curricula|audit_logs|notifications|curriculum_reviews|documents|users)\b/i', $query['query']);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_query_count_does_not_grow_with_faculty_or_program_cards(): void
    {
        $faculty = Faculty::factory()->create();
        StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
        $urls = [route('faculties.index'), route('faculties.show', $faculty->slug)];
        $counts = [];
        foreach ($urls as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get($url)->assertOk();
            $counts[$url] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        Faculty::factory()->count(10)->create();
        StudyProgram::factory()->count(10)->create(['faculty_id' => $faculty->id]);
        foreach ($urls as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get($url)->assertOk();
            $this->assertSame($counts[$url], count(DB::getQueryLog()));
            DB::disableQueryLog();
        }
    }
}
