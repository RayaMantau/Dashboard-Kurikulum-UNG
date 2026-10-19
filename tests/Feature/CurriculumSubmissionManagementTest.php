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
use App\Services\Curriculum\CurriculumSubmissionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumSubmissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    /** @return array{User, Curriculum, Semester, CurriculumCourse, Document} */
    private function ready(CurriculumStatus $status = CurriculumStatus::DRAFT): array
    {
        $actor = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $actor->study_program_id, 'created_by' => $actor->id,
            'status' => $status, 'total_semesters' => 2, 'total_sks' => 144,
            'effective_year' => null, 'description' => null,
        ]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        $course = Course::factory()->create(['study_program_id' => $actor->study_program_id]);
        $attachment = CurriculumCourse::factory()->create([
            'curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id,
            'sks' => 3, 'prerequisite_notes' => 'Lulus pengantar',
        ]);
        $document = Document::factory()->create([
            'curriculum_id' => $curriculum->id, 'uploaded_by' => $actor->id,
            'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf',
        ]);
        Storage::disk('documents')->put($document->file_path, '%PDF-1.4 test content');

        return [$actor, $curriculum, $semester, $attachment, $document];
    }

    private function url(string $action, Curriculum $curriculum): string
    {
        return route('admin.curricula.submit.'.$action, $curriculum);
    }

    public function test_ready_draft_confirmation_and_forged_submission_notify_only_active_same_program_heads(): void
    {
        [$actor, $curriculum] = $this->ready();
        $heads = User::factory()->count(2)->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'is_active' => true, 'study_program_id' => $actor->study_program_id]);
        User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'is_active' => false, 'study_program_id' => $actor->study_program_id]);
        User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        User::factory()->create(['role' => UserRole::FACULTY_ADMIN, 'is_active' => true, 'study_program_id' => $actor->study_program_id]);
        $before = $curriculum->fresh()->getAttributes();
        $response = $this->actingAs($actor)->get($this->url('confirm', $curriculum))->assertOk()
            ->assertSee('Kesiapan Kurikulum')->assertSee('Ajukan untuk Review')
            ->assertSee($actor->studyProgram->name)->assertSee('name="_token"', false)
            ->assertSee('method="POST"', false)->assertSee('tidak berarti persetujuan otomatis');
        $this->assertTrue($response->viewData('readiness')['is_ready']);
        $this->assertCount(4, $response->viewData('readiness')['checks']);
        foreach ($response->viewData('readiness')['checks'] as $check) {
            $this->assertTrue($check['passed']);
        }
        $this->assertSame($before, $curriculum->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->travelTo(now()->startOfSecond());
        $this->post($this->url('store', $curriculum), [
            'status' => 'published', 'study_program_id' => StudyProgram::factory()->create()->id,
            'submitted_at' => '2000-01-01', 'approved_at' => '2000-01-01', 'published_at' => '2000-01-01',
            'created_by' => $heads->first()->id, 'name' => 'FORGED', 'is_ready' => false,
        ])->assertRedirect(route('admin.curricula.index'))->assertSessionHas('success', 'Kurikulum berhasil diajukan untuk review Kaprodi.');
        $curriculum->refresh();
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->status);
        $this->assertTrue($curriculum->submitted_at->equalTo(now()));
        $this->assertSame($actor->study_program_id, $curriculum->study_program_id);
        $this->assertSame($actor->id, $curriculum->created_by);
        $this->assertSame($before['name'], $curriculum->name);
        $this->assertNull($curriculum->approved_at);
        $this->assertNull($curriculum->published_at);
        $audit = AuditLog::sole();
        $this->assertSame('curriculum.submitted', $audit->action);
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame('draft', $audit->old_values['status']);
        $this->assertSame('submitted', $audit->new_values['status']);
        $this->assertEqualsCanonicalizing($heads->modelKeys(), Notification::pluck('user_id')->all());
        foreach (Notification::all() as $notification) {
            $this->assertSame('curriculum_submitted', $notification->type);
            $this->assertSame(['curriculum_id' => $curriculum->id], $notification->data);
        }
        $this->post($this->url('store', $curriculum))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertTrue($curriculum->fresh()->submitted_at->equalTo(now()));
        $this->actingAs($heads->first())->get(route('dashboard'))->assertOk()->assertSee($curriculum->name);
    }

    public static function incomplete(): array
    {
        return [
            'blank name' => ['name', 'metadata', 'Nama kurikulum wajib diisi.'],
            'zero year' => ['year', 'metadata', 'Tahun kurikulum wajib diisi.'],
            'zero total sks' => ['total_sks', 'metadata', 'Total SKS kurikulum harus lebih dari 0.'],
            'zero semesters metadata' => ['total_semesters', 'metadata', 'Jumlah semester harus lebih dari 0.'],
            'semester gap' => ['gap', 'semesters', 'Semester 3 belum tersedia.'],
            'wrong set same count' => ['wrong_set', 'semesters', 'Semester 2 belum tersedia.'],
            'no semesters' => ['semesters', 'semesters', 'Minimal harus terdapat satu semester.'],
            'no courses' => ['courses', 'courses', 'Minimal harus terdapat satu mata kuliah.'],
            'no documents' => ['documents', 'documents', 'Dokumen kurikulum resmi wajib diunggah.'],
        ];
    }

    #[DataProvider('incomplete')]
    public function test_readiness_and_post_agree_on_incomplete_requirements(string $missing, string $check, string $message): void
    {
        [$actor, $curriculum] = $this->ready();
        match ($missing) {
            'name' => $curriculum->update(['name' => '']),
            'year', 'total_sks', 'total_semesters' => $curriculum->update([$missing => 0]),
            'gap' => $this->gap($curriculum),
            'wrong_set' => $curriculum->semesters()->where('semester_number', 2)->update(['semester_number' => 3]),
            'semesters' => $curriculum->semesters()->delete(),
            'courses' => $curriculum->curriculumCourses()->delete(),
            'documents' => $curriculum->documents()->delete(),
        };
        $response = $this->actingAs($actor)->get($this->url('confirm', $curriculum))->assertOk()->assertSee($message)
            ->assertDontSee('action="'.$this->url('store', $curriculum).'"', false);
        $readiness = $response->viewData('readiness');
        $this->assertFalse($readiness['is_ready']);
        $this->assertFalse($readiness['checks'][$check]['passed']);
        $repairRoute = match ($check) {
            'metadata' => 'admin.curricula.edit',
            'semesters' => 'admin.curricula.semesters.index',
            'courses' => 'admin.curricula.courses.index',
            'documents' => 'admin.curricula.documents.index',
        };
        $response->assertSee(route($repairRoute, $curriculum), false);
        $this->followingRedirects()->post($this->url('store', $curriculum), ['is_ready' => true])
            ->assertOk()->assertSee($readiness['errors'][0])->assertSee('role="alert"', false);
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
        $this->assertNull($curriculum->fresh()->submitted_at);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function gap(Curriculum $curriculum): bool
    {
        $curriculum->update(['total_semesters' => 4]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 4]);

        return true;
    }

    public function test_revision_resubmission_preserves_history_snapshots_and_physical_document(): void
    {
        [$actor, $curriculum, $semester, $attachment, $document] = $this->ready(CurriculumStatus::REVISION);
        $curriculum->update(['submitted_at' => now()->subMonth()]);
        $review = CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $actor->id, 'status' => 'revision', 'notes' => 'Perbaiki dokumen.', 'reviewed_at' => now()->subDay()]);
        $before = [$semester->fresh()->getAttributes(), $attachment->fresh()->getAttributes(), $document->fresh()->getAttributes(), $review->fresh()->getAttributes()];
        $this->actingAs($actor)->get($this->url('confirm', $curriculum))->assertOk()->assertSee('Perlu Revisi');
        $this->post($this->url('store', $curriculum))->assertSessionHasNoErrors()->assertRedirect(route('admin.curricula.index'));
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        $this->assertTrue($curriculum->fresh()->submitted_at->greaterThan(now()->subMinute()));
        $this->assertSame($before, [$semester->fresh()->getAttributes(), $attachment->fresh()->getAttributes(), $document->fresh()->getAttributes(), $review->fresh()->getAttributes()]);
        $this->assertSame('%PDF-1.4 test content', Storage::disk('documents')->get($document->file_path));
        $this->assertDatabaseCount('curricula', 1);
        $this->assertDatabaseCount('curriculum_reviews', 1);
        $this->assertDatabaseCount('documents', 1);
    }

    public static function lockedStatuses(): array
    {
        return [[CurriculumStatus::SUBMITTED], [CurriculumStatus::APPROVED], [CurriculumStatus::PUBLISHED], [CurriculumStatus::ARCHIVED]];
    }

    #[DataProvider('lockedStatuses')]
    public function test_locked_status_confirmation_and_post_are_forbidden(CurriculumStatus $status): void
    {
        [$actor, $curriculum] = $this->ready($status);
        $this->actingAs($actor)->get($this->url('confirm', $curriculum))->assertForbidden();
        $this->post($this->url('store', $curriculum))->assertForbidden();
        $this->get(route('admin.curricula.index'))->assertDontSee($this->url('confirm', $curriculum), false);
        $this->assertSame($status, $curriculum->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_guests_other_roles_inactive_missing_scope_and_cross_program_are_rejected(): void
    {
        [$actor, $curriculum] = $this->ready();
        $this->get($this->url('confirm', $curriculum))->assertRedirect(route('login'));
        $this->post($this->url('store', $curriculum))->assertRedirect(route('login'));
        foreach ([
            ['role' => UserRole::UNIVERSITY_ADMIN], ['role' => UserRole::FACULTY_ADMIN],
            ['role' => UserRole::HEAD_OF_STUDY_PROGRAM], ['is_active' => false],
            ['study_program_id' => null], ['study_program_id' => StudyProgram::factory()->create()->id],
        ] as $changes) {
            $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $actor->study_program_id, ...$changes]);
            $this->actingAs($user)->get($this->url('confirm', $curriculum))->assertForbidden();
            $this->post($this->url('store', $curriculum), ['status' => 'submitted'])->assertForbidden();
        }
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_stale_ready_page_cannot_bypass_fresh_document_requirement(): void
    {
        [$actor, $curriculum, , , $document] = $this->ready();
        $this->actingAs($actor)->get($this->url('confirm', $curriculum))->assertOk()->assertSee('Ajukan untuk Review');
        $curriculum->load('documents', 'semesters', 'curriculumCourses');
        $this->delete(route('admin.curricula.documents.destroy', [$curriculum, $document]))->assertSessionHasNoErrors();
        $this->followingRedirects()->post($this->url('store', $curriculum), ['is_ready' => true])->assertOk()->assertSee('Dokumen kurikulum resmi wajib diunggah.');
        $this->assertFalse(app(CurriculumSubmissionService::class)->readiness($curriculum)['checks']['documents']['passed']);
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'curriculum.submitted']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_post_submission_locks_all_content_mutations_but_keeps_readonly_pages(): void
    {
        [$actor, $curriculum, $semester, $attachment, $document] = $this->ready();
        $this->actingAs($actor)->post($this->url('store', $curriculum))->assertSessionHasNoErrors();
        $this->get(route('admin.curricula.edit', $curriculum))->assertForbidden();
        $this->put(route('admin.curricula.update', $curriculum), [])->assertForbidden();
        foreach ([
            ['semesters', $semester], ['courses', $attachment],
        ] as [$area, $record]) {
            $prefix = 'admin.curricula.'.$area.'.';
            $this->get(route($prefix.'create', $curriculum))->assertForbidden();
            $this->get(route($prefix.'edit', [$curriculum, $record]))->assertForbidden();
            $this->post(route($prefix.'store', $curriculum), [])->assertForbidden();
            $this->put(route($prefix.'update', [$curriculum, $record]), [])->assertForbidden();
            $this->delete(route($prefix.'destroy', [$curriculum, $record]))->assertForbidden();
            $this->get(route($prefix.'index', $curriculum))->assertOk();
        }
        $this->post(route('admin.curricula.documents.store', $curriculum), [])->assertForbidden();
        $this->delete(route('admin.curricula.documents.destroy', [$curriculum, $document]))->assertForbidden();
        $this->get(route('admin.curricula.documents.index', $curriculum))->assertOk();
        $this->get(route('admin.curricula.documents.download', [$curriculum, $document]))->assertOk();
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_readiness_uses_three_scoped_queries_and_does_not_scan_document_storage(): void
    {
        [$actor, $curriculum, , , $document] = $this->ready();
        Storage::disk('documents')->delete($document->file_path);
        DB::enableQueryLog();
        $readiness = app(CurriculumSubmissionService::class)->readiness($curriculum);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertTrue($readiness['is_ready']);
        $this->assertCount(3, $queries);
        $this->assertStringContainsString('semester_number', $queries[0]['query']);
        $this->assertStringStartsWith('select exists(', $queries[1]['query']);
        $this->assertStringStartsWith('select exists(', $queries[2]['query']);
        foreach ($queries as $query) {
            $this->assertStringContainsString('curriculum_id', $query['query']);
        }
        $this->actingAs($actor)->post($this->url('store', $curriculum))->assertSessionHasNoErrors();
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
    }

    public function test_index_links_draft_and_revision_to_confirmation_without_executing_submission(): void
    {
        [$actor, $draft] = $this->ready();
        $revision = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id, 'status' => CurriculumStatus::REVISION]);
        $response = $this->actingAs($actor)->get(route('admin.curricula.index'))->assertOk();
        foreach ([$draft, $revision] as $curriculum) {
            $response->assertSee('href="'.$this->url('confirm', $curriculum).'"', false)
                ->assertDontSee('action="'.$this->url('store', $curriculum).'"', false);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function failingSideEffects(): array
    {
        return [[AuditLog::class], [Notification::class]];
    }

    public function test_service_rechecks_stale_actor_and_curriculum_before_submission(): void
    {
        [$actor, $curriculum] = $this->ready();
        $curriculum->load('semesters', 'curriculumCourses', 'documents');
        User::whereKey($actor->id)->update(['is_active' => false]);
        try {
            app(CurriculumSubmissionService::class)->submit($actor, $curriculum);
            $this->fail('Stale active actor must be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Pengguna tidak aktif', $exception->getMessage());
        }
        User::whereKey($actor->id)->update(['is_active' => true]);
        Curriculum::whereKey($curriculum->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        try {
            app(CurriculumSubmissionService::class)->submit($actor, $curriculum);
            $this->fail('Stale draft status must be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('draft atau revision', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('failingSideEffects')]
    public function test_failure_in_audit_or_notification_rolls_back_submission(string $model): void
    {
        [$actor, $curriculum] = $this->ready();
        User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'is_active' => true, 'study_program_id' => $actor->study_program_id]);
        $dispatcher = $model::getEventDispatcher();
        $model::setEventDispatcher(clone $dispatcher);
        $model::creating(fn () => throw new RuntimeException('Side effect unavailable'));
        try {
            app(CurriculumSubmissionService::class)->submit($actor, $curriculum);
            $this->fail('Submission must roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Side effect unavailable', $exception->getMessage());
        } finally {
            $model::setEventDispatcher($dispatcher);
        }
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
        $this->assertNull($curriculum->fresh()->submitted_at);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }
}
