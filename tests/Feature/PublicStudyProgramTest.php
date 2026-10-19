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
use Tests\TestCase;

class PublicStudyProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibility_requires_both_active_program_and_active_faculty(): void
    {
        $visible = StudyProgram::factory()->create(['name' => 'VisibleProgram']);
        $inactive = StudyProgram::factory()->create(['name' => 'HiddenInactive', 'is_active' => false]);
        $parent = Faculty::factory()->create(['is_active' => false, 'name' => 'HiddenFaculty']);
        $hidden = StudyProgram::factory()->create(['faculty_id' => $parent->id, 'name' => 'HiddenParentProgram']);
        $this->get(route('study-programs.index'))->assertOk()->assertSee($visible->name)
            ->assertDontSee($inactive->name)->assertDontSee($hidden->name)->assertDontSee($parent->name);
        $this->get(route('study-programs.show', $visible->slug))->assertOk();
        foreach ([$inactive->slug, $hidden->slug, 'unknown-program', (string) $visible->id] as $slug) {
            $this->get(route('study-programs.show', $slug))->assertNotFound()->assertDontSee('Hidden');
        }
    }

    public function test_internal_users_can_browse_both_public_pages(): void
    {
        $program = StudyProgram::factory()->create();
        foreach (UserRole::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'name' => 'PrivateActor']));
            foreach ([route('study-programs.index'), route('study-programs.show', $program->slug)] as $url) {
                $this->get($url)->assertOk()->assertSee('Dashboard Internal')->assertDontSee('PrivateActor');
            }
        }
    }

    public function test_filter_uses_active_faculty_slug_and_invalid_filters_fail_closed(): void
    {
        $a = StudyProgram::factory()->create(['name' => 'ProgramA']);
        $b = StudyProgram::factory()->create(['name' => 'ProgramB']);
        $hidden = Faculty::factory()->create(['is_active' => false, 'name' => 'HiddenFaculty']);
        StudyProgram::factory()->create(['faculty_id' => $hidden->id, 'name' => 'SecretProgram']);
        $this->get(route('study-programs.index', ['faculty' => $a->faculty->slug]))->assertOk()
            ->assertSee('ProgramA')->assertDontSee('ProgramB')->assertDontSee('HiddenFaculty');
        foreach ([$hidden->slug, 'not-found', ['bad'], (string) $b->faculty_id] as $filter) {
            $this->get(route('study-programs.index', ['faculty' => $filter]))->assertOk()
                ->assertSee('Tidak ada Program Studi yang tersedia untuk fakultas ini.')
                ->assertDontSee('ProgramA')->assertDontSee('ProgramB')->assertDontSee('SecretProgram')->assertDontSee('HiddenFaculty');
        }
    }

    public function test_order_and_pagination_preserve_faculty_filter(): void
    {
        $faculty = Faculty::factory()->create();
        $ids = [];
        foreach (range(1, 13) as $number) {
            $ids[] = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Same Program'])->id;
        }
        $first = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'A Program']);
        StudyProgram::factory()->create(['name' => 'OtherProgram']);
        $response = $this->get(route('study-programs.index', ['faculty' => $faculty->slug]))->assertOk();
        $paginator = $response->viewData('studyPrograms');
        $this->assertSame(14, $paginator->total());
        $this->assertSame(12, $paginator->perPage());
        $this->assertSame([$first->id, ...array_slice($ids, 0, 11)], $paginator->getCollection()->modelKeys());
        $this->assertStringContainsString('faculty='.$faculty->slug, $paginator->nextPageUrl());
        $second = $this->get($paginator->nextPageUrl())->assertOk()->assertDontSee('OtherProgram');
        $this->assertSame(array_slice($ids, 11), $second->viewData('studyPrograms')->getCollection()->modelKeys());
    }

    public function test_only_published_is_current_even_when_other_states_have_newer_years(): void
    {
        $program = StudyProgram::factory()->create();
        foreach (CurriculumStatus::cases() as $status) {
            Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status,
                'name' => 'Version-'.$status->value, 'year' => $status === CurriculumStatus::PUBLISHED ? 2020 : 2030,
                'effective_year' => 2021, 'total_sks' => 144, 'total_semesters' => 8]);
        }
        $index = $this->get(route('study-programs.index'))->assertOk()->assertSee('Kurikulum tersedia');
        $this->assertTrue($index->viewData('studyPrograms')->first()->has_current_curriculum);
        $detail = $this->get(route('study-programs.show', $program->slug))->assertOk()
            ->assertSee('Version-published')->assertSee('Tahun: 2020')->assertSee('Tahun berlaku: 2021')
            ->assertSee('Total SKS: 144.00')->assertSee('Jumlah semester: 8');
        foreach (CurriculumStatus::cases() as $status) {
            $index->assertDontSee('Version-'.$status->value);
            if ($status !== CurriculumStatus::PUBLISHED) {
                $detail->assertDontSee('Version-'.$status->value);
            }
        }
        $detail->assertSee('Lihat Semua Kurikulum')
            ->assertSee('href="'.route('study-programs.curricula.index', $program->slug).'"', false);
    }

    public function test_duplicate_published_rows_select_latest_publication_then_id_without_repair(): void
    {
        $program = StudyProgram::factory()->create();
        $base = ['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED];
        Curriculum::factory()->create([...$base, 'name' => 'OldPublication', 'published_at' => '2020-01-01', 'year' => 2050]);
        Curriculum::factory()->create([...$base, 'name' => 'EarlierId', 'published_at' => '2021-01-01']);
        Curriculum::factory()->create([...$base, 'name' => 'ChosenPublication', 'published_at' => '2021-01-01']);
        $this->get(route('study-programs.show', $program->slug))->assertOk()->assertSee('ChosenPublication')
            ->assertDontSee('OldPublication')->assertDontSee('EarlierId');
        $this->assertSame(3, Curriculum::where('status', CurriculumStatus::PUBLISHED)->count());
    }

    public function test_empty_states_and_all_nonpublished_statuses_do_not_indicate_availability(): void
    {
        $this->get(route('study-programs.index'))->assertOk()->assertSee('Belum ada Program Studi yang tersedia.');
        $faculty = Faculty::factory()->create();
        $this->get(route('study-programs.index', ['faculty' => $faculty->slug]))->assertOk()
            ->assertSee('Tidak ada Program Studi yang tersedia untuk fakultas ini.');
        $program = StudyProgram::factory()->create();
        foreach (CurriculumStatus::cases() as $status) {
            if ($status !== CurriculumStatus::PUBLISHED) {
                Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status]);
            }
        }
        $this->get(route('study-programs.index'))->assertOk()->assertSee('Kurikulum belum tersedia');
        $this->get(route('study-programs.show', $program->slug))->assertOk()->assertSee('Belum ada kurikulum aktif yang dipublikasikan.');
    }

    public function test_profile_is_escaped_and_website_accepts_only_valid_http_https(): void
    {
        $program = StudyProgram::factory()->create(['description' => '<script>PROFILE</script>', 'accreditation' => '<b>ACCREDITATION</b>']);
        foreach (['https://example.com/program', 'http://example.com'] as $website) {
            $program->update(['website' => $website]);
            $this->get(route('study-programs.show', $program->slug))->assertOk()->assertSee('href="'.$website.'"', false)
                ->assertSee($program->description)->assertDontSee($program->description, false)
                ->assertSee($program->accreditation)->assertDontSee($program->accreditation, false)
                ->assertSee('href="'.route('faculties.show', $program->faculty->slug).'"', false);
        }
        foreach (['javascript:alert(1)', 'data:text/html,test', 'ftp://example.com', '//example.com', 'not a url', null] as $website) {
            $program->update(['website' => $website]);
            $this->get(route('study-programs.show', $program->slug))->assertOk()->assertDontSee('Website Program Studi');
        }
    }

    public function test_navigation_and_faculty_links_are_public_and_only_active_programs_link(): void
    {
        $program = StudyProgram::factory()->create();
        $hidden = StudyProgram::factory()->create(['faculty_id' => $program->faculty_id, 'is_active' => false]);
        $this->get(route('faculties.show', $program->faculty->slug))->assertOk()
            ->assertSee('href="'.route('study-programs.show', $program->slug).'"', false)
            ->assertDontSee('href="'.route('study-programs.show', $hidden->slug).'"', false);
        $this->get(route('home'))->assertOk()->assertSee('href="'.route('study-programs.index').'"', false)
            ->assertSee('href="'.route('faculties.index').'"', false)
            ->assertSee('href="'.route('search').'"', false)->assertDontSee('Segera');
    }

    public function test_public_reads_preserve_records_and_do_not_query_private_workflow_tables(): void
    {
        $program = StudyProgram::factory()->create();
        Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'description' => 'SECRET-METADATA']);
        $tables = ['faculties', 'study_programs', 'curricula', 'audit_logs', 'notifications', 'curriculum_reviews'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ([route('study-programs.index'), route('study-programs.show', $program->slug)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('SECRET-')->assertDontSee('/storage/')
                ->assertDontSee('/internal/')->assertDontSee('/university-admin/');
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(audit_logs|notifications|curriculum_reviews|documents|users)\b/i', $query['query']);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_directory_query_count_does_not_grow_per_card(): void
    {
        StudyProgram::factory()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('study-programs.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        StudyProgram::factory()->count(10)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('study-programs.index'))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
