<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function actor(?StudyProgram $program = null, UserRole $role = UserRole::STUDY_PROGRAM_ADMIN): User
    {
        $program ??= StudyProgram::factory()->create();

        return User::factory()->create(['role' => $role, 'is_active' => true,
            'study_program_id' => $program->id, 'faculty_id' => $program->faculty_id]);
    }

    private function metadata(): array
    {
        return ['name' => 'Versi Perbaikan', 'year' => 2026, 'total_sks' => 144, 'total_semesters' => 8,
            'ratified_at' => '2026-07-15', 'external_document_url' => 'https://example.test/kurikulum.pdf'];
    }

    public static function dates(): array
    {
        return [
            'before minor' => ['2026-07-15', '2028-07-14', 'upcoming', '2028-07-15', '2030-07-15'],
            'minor boundary' => ['2026-07-15', '2028-07-15', 'minor', '2028-07-15', '2030-07-15'],
            'between' => ['2026-07-15', '2029-07-15', 'minor', '2028-07-15', '2030-07-15'],
            'reconstruction boundary' => ['2026-07-15', '2030-07-15', 'reconstruction', '2028-07-15', '2030-07-15'],
            'after' => ['2026-07-15', '2031-01-01', 'reconstruction', '2028-07-15', '2030-07-15'],
            'leap day minor' => ['2024-02-29', '2026-02-28', 'minor', '2026-02-28', '2028-02-29'],
            'leap day reconstruction' => ['2024-02-29', '2028-02-29', 'reconstruction', '2026-02-28', '2028-02-29'],
        ];
    }

    #[DataProvider('dates')]
    public function test_monev_boundaries_are_date_only_and_do_not_mutate_ratification(string $ratified, string $today, string $state, string $minor, string $reconstruction): void
    {
        $this->travelTo(CarbonImmutable::parse($today.' 00:00:00', config('app.timezone')));
        $curriculum = new Curriculum(['ratified_at' => $ratified]);
        $schedule = app(CurriculumMonitoringService::class)->schedule($curriculum);
        $this->assertSame($state, $schedule['state']);
        $this->assertSame($minor, $schedule['minor']->toDateString());
        $this->assertSame($reconstruction, $schedule['reconstruction']->toDateString());
        $this->assertSame($ratified, $curriculum->ratified_at->toDateString());
    }

    public function test_null_legacy_data_and_empty_programs_render_safely(): void
    {
        $this->get(route('monitoring.index'))->assertOk()->assertSee('Belum ada Program Studi yang tersedia.');
        $program = StudyProgram::factory()->create();
        $this->get(route('monitoring.index'))->assertOk()->assertSee('Belum ada kurikulum yang dipublikasikan.');
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED]);
        $this->assertNull($curriculum->fresh()->ratified_at);
        $this->assertNull($curriculum->fresh()->external_document_url);
        $schedule = app(CurriculumMonitoringService::class)->schedule($curriculum);
        $this->assertNull($schedule['minor']);
        $this->assertNull($schedule['reconstruction']);
        $this->get(route('monitoring.index'))->assertOk()->assertSee('Belum ditetapkan')
            ->assertSee('Belum tersedia')->assertSee('Belum ada tanggal pengesahan')->assertSee('Belum dapat dihitung');
    }

    public function test_public_table_has_required_columns_safe_links_and_controlled_document_access(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-07-15'));
        $curriculum = Curriculum::factory()->create([...$this->metadata(), 'status' => CurriculumStatus::PUBLISHED]);
        $program = $curriculum->studyProgram;
        Storage::fake('documents');
        $pdf = "%PDF-1.4\n%%EOF";
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id,
            'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf',
            'file_name' => 'Kurikulum.pdf', 'file_type' => 'application/pdf']);
        Storage::disk('documents')->put($document->file_path, $pdf);
        $document->uploader->update(['name' => 'SECRET-UPLOADER']);
        $detail = route('study-programs.curricula.show', [$program->slug, $curriculum->id]);
        $response = $this->get(route('monitoring.index'))->assertOk();
        foreach (['Program Studi', 'Tahun Perbaikan Kurikulum', 'Tanggal Pengesahan', 'Dokumen', 'Link File Kurikulum', 'Monev Kurikulum', '2026', '15 Juli 2026', '15 Juli 2028', '15 Juli 2030', 'Monev Minor'] as $text) {
            $response->assertSee($text);
        }
        $response->assertSee($detail.'#documents-title', false)->assertSee('Lihat Dokumen (1)')
            ->assertSee('href="https://example.test/kurikulum.pdf"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee($document->file_path)->assertDontSee('SECRET-UPLOADER')->assertDontSee('/storage/');
        $download = route('study-programs.curricula.documents.download', [$program->slug, $curriculum->id, $document->id]);
        $this->get($detail)->assertOk()->assertSee($download, false);
        $this->get($download)->assertOk()->assertDownload('Kurikulum.pdf');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function workflowStatuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('workflowStatuses')]
    public function test_monitoring_shows_only_current_published_even_when_logged_in(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create([...$this->metadata(), 'name' => 'VERSION-SENTINEL', 'status' => $status]);
        $url = route('monitoring.index', ['status' => $status->value]);
        foreach ([null, $this->actor(role: UserRole::UNIVERSITY_ADMIN)] as $user) {
            if ($user) {
                $this->actingAs($user);
            }
            $response = $this->get($url)->assertOk();
            if ($status === CurriculumStatus::PUBLISHED) {
                $response->assertSee('VERSION-SENTINEL')->assertSee($curriculum->external_document_url);
            } else {
                $response->assertDontSee('VERSION-SENTINEL')->assertDontSee($curriculum->external_document_url);
            }
        }
        if ($status === CurriculumStatus::ARCHIVED) {
            $this->get(route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]))
                ->assertOk()->assertSee('15 Juli 2026')->assertSee($curriculum->external_document_url);
        }
    }

    public function test_inactive_parents_are_hidden_and_duplicate_publications_choose_one_deterministically(): void
    {
        $program = StudyProgram::factory()->create(['name' => 'ACTIVE-PROGRAM']);
        Curriculum::factory()->create(['study_program_id' => $program->id, 'name' => 'OLDER-PUBLISHED', 'status' => CurriculumStatus::PUBLISHED, 'published_at' => '2025-01-01']);
        $current = Curriculum::factory()->create(['study_program_id' => $program->id, 'name' => 'CURRENT-PUBLISHED', 'status' => CurriculumStatus::PUBLISHED, 'published_at' => '2026-01-01']);
        Curriculum::factory()->create(['study_program_id' => $program->id, 'name' => 'OLDER-DATE-NEWER-ID', 'status' => CurriculumStatus::PUBLISHED, 'published_at' => '2024-01-01']);
        $inactive = StudyProgram::factory()->create(['is_active' => false, 'name' => 'INACTIVE-PROGRAM']);
        $hiddenFaculty = StudyProgram::factory()->create(['faculty_id' => Faculty::factory()->create(['is_active' => false])->id, 'name' => 'INACTIVE-FACULTY-PROGRAM']);
        foreach ([$inactive, $hiddenFaculty] as $hidden) {
            Curriculum::factory()->create(['study_program_id' => $hidden->id, 'status' => CurriculumStatus::PUBLISHED, 'name' => 'HIDDEN-PUBLICATION']);
        }
        $response = $this->get(route('monitoring.index'))->assertOk()->assertSee('CURRENT-PUBLISHED')
            ->assertDontSee('OLDER-PUBLISHED')->assertDontSee('OLDER-DATE-NEWER-ID')->assertDontSee('HIDDEN-PUBLICATION')
            ->assertDontSee('INACTIVE-PROGRAM')->assertDontSee('INACTIVE-FACULTY-PROGRAM');
        $this->assertCount(1, $response->viewData('programs'));
        $this->assertTrue($response->viewData('programs')->first()->curricula->sole()->is($current));
    }

    public function test_pagination_order_and_query_count_do_not_grow_per_program(): void
    {
        $program = StudyProgram::factory()->create(['name' => 'Program 00']);
        Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED]);
        DB::enableQueryLog();
        $this->get(route('monitoring.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 16) as $number) {
            $additional = StudyProgram::factory()->create(['name' => sprintf('Program %02d', $number), 'faculty_id' => $program->faculty_id]);
            Curriculum::factory()->create(['study_program_id' => $additional->id, 'status' => CurriculumStatus::PUBLISHED]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('monitoring.index'))->assertOk()->assertSee('page=2', false);
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertCount(15, $response->viewData('programs'));
        $this->assertSame('Program 00', $response->viewData('programs')->first()->name);
        $this->get(route('monitoring.index', ['page' => 2]))->assertOk()->assertSee('Program 15')->assertSee('Program 16')->assertDontSee('Program 00');
    }

    public static function validUrls(): array
    {
        return [['http://example.test/file.pdf'], ['https://example.test/file.pdf?a=1&b=%22test%22']];
    }

    #[DataProvider('validUrls')]
    public function test_admin_prodi_can_create_and_update_monitoring_metadata_with_audit(string $url): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->get(route('admin.curricula.create'))->assertOk()->assertSee('Tanggal Pengesahan')->assertSee('Link File Kurikulum');
        $this->post(route('admin.curricula.store'), [...$this->metadata(), 'external_document_url' => $url, 'study_program_id' => 999, 'approved_at' => '2020-01-01'])
            ->assertSessionHasNoErrors();
        $curriculum = Curriculum::sole();
        $this->assertSame($actor->study_program_id, $curriculum->study_program_id);
        $this->assertSame('2026-07-15', $curriculum->ratified_at->toDateString());
        $this->assertSame($url, $curriculum->external_document_url);
        $this->assertNull($curriculum->approved_at);
        $this->assertSame($url, AuditLog::sole()->new_values['external_document_url']);
        $curriculum->update(['status' => CurriculumStatus::REVISION]);
        $this->put(route('admin.curricula.update', $curriculum), [...$this->metadata(), 'year' => 2027, 'ratified_at' => '2027-08-12', 'external_document_url' => null])->assertSessionHasNoErrors();
        $curriculum->refresh();
        $this->assertSame(2027, $curriculum->year);
        $this->assertSame('2027-08-12', $curriculum->ratified_at->toDateString());
        $this->assertNull($curriculum->external_document_url);
        $this->assertSame(CurriculumStatus::REVISION, $curriculum->status);
        $audit = AuditLog::where('action', 'curriculum.updated')->sole();
        $this->assertSame('2026-07-15', CarbonImmutable::parse($audit->old_values['ratified_at'])->toDateString());
        $this->assertSame('2027-08-12', CarbonImmutable::parse($audit->new_values['ratified_at'])->toDateString());
        $this->assertSame(2027, $audit->new_values['year']);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('curriculum_reviews', 0);
    }

    public static function invalidMetadata(): array
    {
        return [
            ['ratified_at', 'not-a-date'], ['ratified_at', '2026-02-30'], ['ratified_at', ['2026-01-01']],
            ['ratified_at', '2026-01-01 12:00:00'], ['ratified_at', '9996-01-01'],
            ['external_document_url', 'not-a-url'], ['external_document_url', 'javascript:alert(1)'],
            ['external_document_url', 'data:text/html,test'], ['external_document_url', 'file:///etc/passwd'],
            ['external_document_url', '//example.test/file.pdf'], ['external_document_url', ['https://example.test']],
            ['external_document_url', 'https://example.test/'.str_repeat('x', 2048)],
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function test_invalid_monitoring_input_is_rejected_without_write(string $field, mixed $value): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->from(route('admin.curricula.create'))
            ->post(route('admin.curricula.store'), [...$this->metadata(), $field => $value])
            ->assertSessionHasErrors($field);
        $this->get(route('admin.curricula.create'))->assertOk();
        $this->assertDatabaseCount('curricula', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_anonymous_mutations_cross_program_and_other_roles_are_denied(): void
    {
        $curriculum = Curriculum::factory()->create();
        $this->post(route('admin.curricula.store'), $this->metadata())->assertRedirect(route('login'));
        $this->put(route('admin.curricula.update', $curriculum), $this->metadata())->assertRedirect(route('login'));
        $this->post(route('monitoring.index'), $this->metadata())->assertStatus(405);
        $this->actingAs($this->actor())->put(route('admin.curricula.update', $curriculum), $this->metadata())->assertForbidden();
        foreach ([UserRole::UNIVERSITY_ADMIN, UserRole::FACULTY_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM] as $role) {
            $this->actingAs($this->actor($curriculum->studyProgram, $role))
                ->put(route('admin.curricula.update', $curriculum), $this->metadata())->assertForbidden();
        }
        $this->assertNull($curriculum->fresh()->ratified_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('workflowStatuses')]
    public function test_new_fields_do_not_bypass_existing_metadata_status_lock(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status]);
        $this->actingAs($this->actor($curriculum->studyProgram));
        $response = $this->put(route('admin.curricula.update', $curriculum), $this->metadata());
        if (in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true)) {
            $response->assertSessionHasNoErrors();
            $this->assertSame('2026-07-15', $curriculum->fresh()->ratified_at->toDateString());
        } else {
            $response->assertForbidden();
            $this->assertNull($curriculum->fresh()->ratified_at);
        }
        $this->assertSame($status, $curriculum->fresh()->status);
    }

    public function test_faculty_monitoring_is_scoped_and_all_programs_are_accessible_by_pagination(): void
    {
        $program = StudyProgram::factory()->create(['name' => 'Own 00']);
        Curriculum::factory()->create([...$this->metadata(), 'study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED]);
        StudyProgram::factory()->create(['name' => 'OTHER-FACULTY-SECRET']);
        foreach (range(1, 15) as $number) {
            StudyProgram::factory()->create(['faculty_id' => $program->faculty_id, 'name' => sprintf('Own %02d', $number)]);
        }
        $this->actingAs($this->actor($program, UserRole::FACULTY_ADMIN))->get(route('dashboard', ['faculty_id' => 999]))
            ->assertOk()->assertSee('Tanggal Pengesahan')->assertSee('15 Juli 2026')->assertSee('Status Versi Terbaru')
            ->assertDontSee('OTHER-FACULTY-SECRET')->assertSee('page=2', false);
        $this->get(route('dashboard', ['page' => 2]))->assertOk()->assertSee('Own 15')->assertDontSee('OTHER-FACULTY-SECRET');
    }

    public function test_public_rendering_escapes_data_and_suppresses_unsafe_legacy_url(): void
    {
        $program = StudyProgram::factory()->create(['name' => '<script>alert(1)</script>']);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED,
            'external_document_url' => 'javascript:alert(1)']);
        $this->get(route('monitoring.index'))->assertOk()->assertSee($program->name)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:alert(1)', false);
        $url = 'https://example.test/file?a=1&b=2';
        $curriculum->update(['external_document_url' => $url]);
        $this->get(route('monitoring.index'))->assertOk()->assertSee('href="'.e($url).'"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }
}
