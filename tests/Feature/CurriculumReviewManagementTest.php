<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumReviewService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    /** @return array{User, User, Curriculum, Semester, CurriculumCourse, Document} */
    private function fixture(): array
    {
        $program = StudyProgram::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id, 'is_active' => true]);
        $head = User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id, 'is_active' => true]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED, 'submitted_at' => now(), 'total_semesters' => 2, 'created_by' => $admin->id]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        $course = Course::factory()->create(['study_program_id' => $program->id, 'code' => 'SNAP101', 'name' => 'Identitas Snapshot']);
        $attachment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id, 'uploaded_by' => $admin->id, 'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf']);
        Storage::disk('documents')->put($document->file_path, '%PDF-1.7 review');

        return [$admin, $head, $curriculum, $semester, $attachment, $document];
    }

    public function test_queue_is_scoped_submitted_only_ordered_and_paginated(): void
    {
        [, $head, $curriculum] = $this->fixture();
        $others = Curriculum::factory()->count(16)->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::SUBMITTED, 'submitted_at' => now()->subDay()]);
        $legacy = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::SUBMITTED, 'submitted_at' => null]);
        foreach (CurriculumStatus::cases() as $status) {
            if ($status !== CurriculumStatus::SUBMITTED) {
                Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => $status]);
            }
        }
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::SUBMITTED, 'name' => 'Rahasia Prodi Lain']);
        $response = $this->actingAs($head)->get(route('kaprodi.submissions.index', ['study_program_id' => $foreign->study_program_id]))->assertOk()
            ->assertSee($head->studyProgram->name)->assertDontSee($foreign->name);
        $page = $response->viewData('curricula');
        $this->assertSame(18, $page->total());
        $this->assertCount(15, $page->items());
        $this->assertSame(array_merge([$legacy->id], $others->take(14)->modelKeys()), $page->getCollection()->modelKeys());
        $this->get(route('kaprodi.submissions.index', ['page' => 2]))->assertOk()->assertSee($curriculum->name)->assertDontSee($foreign->name);
    }

    public function test_detail_uses_snapshots_and_private_document_links_without_edit_controls(): void
    {
        [, $head, $curriculum, , $attachment, $document] = $this->fixture();
        $attachment->course->update(['code' => 'MASTER999', 'name' => 'Master Diganti']);
        $this->actingAs($head)->get(route('kaprodi.submissions.show', $curriculum))->assertOk()
            ->assertSee('SNAP101')->assertSee('Identitas Snapshot')->assertDontSee('MASTER999')->assertDontSee('Master Diganti')
            ->assertSee('Belum ada mata kuliah pada semester ini.')->assertSee($document->title)
            ->assertSee(route('kaprodi.submissions.documents.download', [$curriculum, $document]), false)
            ->assertDontSee($document->file_path)->assertSee('Konfirmasi Persetujuan')
            ->assertDontSee(route('admin.curricula.edit', $curriculum), false);
    }

    public function test_empty_queue_and_empty_structure_render(): void
    {
        [, $head, $curriculum] = $this->fixture();
        $curriculum->update(['status' => CurriculumStatus::APPROVED]);
        $this->actingAs($head)->get(route('kaprodi.submissions.index'))->assertOk()->assertSee('Tidak ada kurikulum yang menunggu review.');
        $empty = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::SUBMITTED]);
        $this->get(route('kaprodi.submissions.show', $empty))->assertOk()->assertSee('Belum ada semester')->assertSee('Belum ada dokumen');
    }

    /** @return array<string, array{UserRole}> */
    public static function otherRoles(): array
    {
        return ['university' => [UserRole::UNIVERSITY_ADMIN], 'faculty' => [UserRole::FACULTY_ADMIN], 'prodi' => [UserRole::STUDY_PROGRAM_ADMIN]];
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_use_review_surface(UserRole $role): void
    {
        [, , $curriculum, , , $document] = $this->fixture();
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'study_program_id' => $curriculum->study_program_id]);
        $this->actingAs($user)->get(route('kaprodi.submissions.index'))->assertForbidden();
        $this->get(route('kaprodi.submissions.show', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.approve', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => 'Revisi'])->assertForbidden();
        $this->get(route('kaprodi.submissions.documents.download', [$curriculum, $document]))->assertForbidden();
        $this->assertDatabaseCount('curriculum_reviews', 0);
    }

    public function test_guests_missing_scope_inactive_and_cross_prodi_are_denied(): void
    {
        [, $head, $curriculum, , , $document] = $this->fixture();
        $this->get(route('kaprodi.submissions.index'))->assertRedirect(route('login'));
        $this->post(route('kaprodi.submissions.approve', $curriculum))->assertRedirect(route('login'));
        $head->update(['study_program_id' => null]);
        $this->actingAs($head)->get(route('kaprodi.submissions.index'))->assertForbidden();
        $this->post(route('kaprodi.submissions.approve', $curriculum))->assertForbidden();
        $head->update(['study_program_id' => StudyProgram::factory()->create()->id]);
        $this->actingAs($head->fresh())->get(route('kaprodi.submissions.show', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.approve', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => 'Revisi'])->assertForbidden();
        $this->get(route('kaprodi.submissions.documents.download', [$curriculum, $document]))->assertForbidden();
        $head->update(['is_active' => false]);
        $this->actingAs($head->fresh())->get(route('kaprodi.submissions.index'))->assertForbidden();
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function lockedStatuses(): array
    {
        return collect(CurriculumStatus::cases())->reject(fn (CurriculumStatus $status): bool => $status === CurriculumStatus::SUBMITTED)
            ->mapWithKeys(fn (CurriculumStatus $status): array => [$status->value => [$status]])->all();
    }

    #[DataProvider('lockedStatuses')]
    public function test_only_submitted_curriculum_can_be_reviewed_or_downloaded(CurriculumStatus $status): void
    {
        [, $head, $curriculum, , , $document] = $this->fixture();
        $curriculum->update(['status' => $status]);
        $this->actingAs($head)->get(route('kaprodi.submissions.show', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.approve', $curriculum))->assertForbidden();
        $this->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => 'Revisi'])->assertForbidden();
        $this->get(route('kaprodi.submissions.documents.download', [$curriculum, $document]))->assertForbidden();
        $this->assertDatabaseCount('curriculum_reviews', 0);
    }

    /** @return array<string, array{string, CurriculumStatus, string, string}> */
    public static function decisions(): array
    {
        return [
            'approve' => ['approve', CurriculumStatus::APPROVED, 'curriculum.approved', 'curriculum_approved'],
            'revision' => ['revision', CurriculumStatus::REVISION, 'curriculum.revision_requested', 'curriculum_revision_requested'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_decision_is_atomic_scoped_ignores_forged_fields_and_preserves_children(string $action, CurriculumStatus $status, string $event, string $notification): void
    {
        [$admin, $head, $curriculum, $semester, $attachment, $document] = $this->fixture();
        $second = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $admin->study_program_id, 'is_active' => true]);
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $admin->study_program_id, 'is_active' => false]);
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => StudyProgram::factory(), 'is_active' => true]);
        $published = Curriculum::factory()->create(['study_program_id' => $admin->study_program_id, 'status' => CurriculumStatus::PUBLISHED]);
        $before = [$semester->fresh()->getAttributes(), $attachment->fresh()->getAttributes(), $document->fresh()->getAttributes()];
        $this->actingAs($head)->post(route('kaprodi.submissions.'.$action, $curriculum), [
            'notes' => 'Perbaiki distribusi SKS.', 'status' => 'published', 'reviewer_id' => $admin->id,
            'study_program_id' => $published->id, 'published_at' => now()->toDateTimeString(), 'approved_at' => '2000-01-01',
        ])->assertRedirect(route('kaprodi.submissions.index'))->assertSessionHas('success');
        $curriculum->refresh();
        $this->assertSame($status, $curriculum->status);
        $this->assertSame($admin->study_program_id, $curriculum->study_program_id);
        $this->assertNull($curriculum->published_at);
        $this->assertNull($curriculum->archived_at);
        $this->assertSame(CurriculumStatus::PUBLISHED, $published->fresh()->status);
        $this->assertSame($action === 'approve', $curriculum->approved_at !== null);
        $this->assertDatabaseHas('curriculum_reviews', ['curriculum_id' => $curriculum->id, 'reviewer_id' => $head->id, 'status' => $status->value, 'notes' => $action === 'revision' ? 'Perbaiki distribusi SKS.' : null]);
        $this->assertDatabaseHas('audit_logs', ['action' => $event, 'entity_id' => $curriculum->id, 'user_id' => $head->id]);
        $audit = AuditLog::where('action', $event)->sole();
        $this->assertSame('submitted', $audit->old_values['status']);
        $this->assertSame($status->value, $audit->new_values['status']);
        $this->assertNotNull($curriculum->reviews()->sole()->reviewed_at);
        $this->assertEqualsCanonicalizing([$admin->id, $second->id], Notification::where('type', $notification)->pluck('user_id')->all());
        $this->assertSame($before, [$semester->fresh()->getAttributes(), $attachment->fresh()->getAttributes(), $document->fresh()->getAttributes()]);
        Storage::disk('documents')->assertExists($document->file_path);
        $this->get(route('kaprodi.submissions.index'))->assertDontSee($curriculum->name);
        $this->post(route('kaprodi.submissions.'.$action, $curriculum), ['notes' => 'Ulang'])->assertForbidden();
        $this->assertDatabaseCount('curriculum_reviews', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_revision_validation_and_service_whitespace_guard(): void
    {
        [, $head, $curriculum] = $this->fixture();
        $this->actingAs($head);
        foreach ([null, '   ', [], str_repeat('a', 10001)] as $notes) {
            $this->from(route('kaprodi.submissions.show', $curriculum))->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => $notes])
                ->assertRedirect(route('kaprodi.submissions.show', $curriculum))->assertSessionHasErrors('notes');
        }
        try {
            app(CurriculumReviewService::class)->requestRevision($head, $curriculum, " \t\n ");
            $this->fail('Whitespace must be rejected by the service.');
        } catch (DomainException) {
            $this->assertDatabaseCount('curriculum_reviews', 0);
            $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        }
        $this->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => str_repeat('a', 10000)])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('curriculum_reviews', 1);
    }

    #[DataProvider('decisions')]
    public function test_service_rechecks_fresh_curriculum_and_actor(string $action, CurriculumStatus $status, string $event, string $notification): void
    {
        [, $head, $curriculum] = $this->fixture();
        $service = app(CurriculumReviewService::class);
        foreach ([['status' => CurriculumStatus::APPROVED], ['status' => CurriculumStatus::REVISION]] as $changes) {
            Curriculum::whereKey($curriculum->id)->update($changes);
            try {
                $action === 'approve' ? $service->approve($head, $curriculum) : $service->requestRevision($head, $curriculum, 'Revisi');
                $this->fail('Stale curriculum must be rejected.');
            } catch (DomainException) {
                $this->assertSame($changes['status'], $curriculum->fresh()->status);
            }
        }
        Curriculum::whereKey($curriculum->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        foreach ([['is_active' => false], ['role' => UserRole::STUDY_PROGRAM_ADMIN], ['study_program_id' => null]] as $changes) {
            User::whereKey($head->id)->update(array_merge(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $curriculum->study_program_id], $changes));
            try {
                $action === 'approve' ? $service->approve($head, $curriculum) : $service->requestRevision($head, $curriculum, 'Revisi');
                $this->fail('Stale actor must be rejected.');
            } catch (DomainException) {
                $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
            }
        }
        $this->assertDatabaseCount('curriculum_reviews', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('decisions')]
    public function test_audit_failure_rolls_back_entire_review(string $action, CurriculumStatus $status, string $event, string $notification): void
    {
        [, $head, $curriculum] = $this->fixture();
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $service = app(CurriculumReviewService::class);
            $action === 'approve' ? $service->approve($head, $curriculum) : $service->requestRevision($head, $curriculum, 'Revisi');
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        $this->assertDatabaseCount('curriculum_reviews', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_private_download_checks_parent_and_missing_or_unsafe_paths(): void
    {
        [, $head, $curriculum, , , $document] = $this->fixture();
        $url = route('kaprodi.submissions.documents.download', [$curriculum, $document]);
        $this->actingAs($head)->get($url.'?file_path=../../.env')->assertOk()->assertDownload($document->file_name)->assertStreamedContent('%PDF-1.7 review');
        $other = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::SUBMITTED]);
        $this->get(route('kaprodi.submissions.documents.download', [$other, $document]))->assertForbidden();
        $this->get(route('admin.curricula.documents.download', [$curriculum, $document]))->assertForbidden();
        Storage::disk('documents')->delete($document->file_path);
        $this->get($url)->assertNotFound();
        $document->update(['file_path' => '../.env']);
        $this->get($url)->assertNotFound();
    }

    #[DataProvider('decisions')]
    public function test_successful_review_cannot_be_repeated_with_original_instance(string $action, CurriculumStatus $status, string $event, string $notification): void
    {
        [, $head, $curriculum] = $this->fixture();
        $service = app(CurriculumReviewService::class);
        $action === 'approve' ? $service->approve($head, $curriculum) : $service->requestRevision($head, $curriculum, 'Revisi');
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->status);
        try {
            $action === 'approve' ? $service->approve($head, $curriculum) : $service->requestRevision($head, $curriculum, 'Ulang');
            $this->fail('A stale reviewed instance cannot be reviewed twice.');
        } catch (DomainException) {
            $this->assertSame($status, $curriculum->fresh()->status);
        }
        $this->assertDatabaseCount('curriculum_reviews', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_failure_rolls_back_review_and_audit(): void
    {
        [, $head, $curriculum] = $this->fixture();
        $dispatcher = Notification::getEventDispatcher();
        Notification::setEventDispatcher(clone $dispatcher);
        Notification::creating(fn () => throw new RuntimeException('Notification unavailable'));
        try {
            app(CurriculumReviewService::class)->approve($head, $curriculum);
            $this->fail('Notification failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Notification unavailable', $exception->getMessage());
        } finally {
            Notification::setEventDispatcher($dispatcher);
        }
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        $this->assertDatabaseCount('curriculum_reviews', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_detail_query_count_does_not_grow_with_semesters_or_courses(): void
    {
        [, $head, $curriculum, , $attachment] = $this->fixture();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($head->fresh())->get(route('kaprodi.submissions.show', $curriculum))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(3, 6) as $number) {
            $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => $number]);
            $course = Course::factory()->create(['study_program_id' => $head->study_program_id]);
            CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($head->fresh())->get(route('kaprodi.submissions.show', $curriculum))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_revision_resubmit_approve_loop_preserves_history_and_locks_all_mutations(): void
    {
        [$admin, $head, $curriculum, $semester, $attachment, $document] = $this->fixture();
        $this->actingAs($head)->get(route('dashboard'))->assertOk()->assertViewHas('metrics', ['Menunggu Review' => 1, 'Disetujui' => 0, 'Dipublikasikan' => 0]);
        $this->actingAs($head)->post(route('kaprodi.submissions.revision', $curriculum), ['notes' => 'Perbaiki metadata.'])->assertRedirect();
        $this->actingAs($admin)->get(route('admin.curricula.edit', $curriculum))->assertOk();
        $this->get(route('admin.curricula.semesters.edit', [$curriculum, $semester]))->assertOk();
        $this->get(route('admin.curricula.courses.edit', [$curriculum, $attachment]))->assertOk();
        $this->get(route('admin.curricula.documents.create', $curriculum))->assertOk();
        $this->put(route('admin.curricula.update', $curriculum), [
            'name' => 'Metadata Diperbaiki', 'year' => 2026, 'effective_year' => 2026,
            'total_sks' => 144, 'total_semesters' => 2, 'description' => 'Revisi metadata',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(CurriculumStatus::REVISION, $curriculum->fresh()->status);
        $this->put(route('admin.curricula.semesters.update', [$curriculum, $semester]), [
            'semester_number' => 1, 'total_sks' => 24,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('admin.curricula.courses.update', [$curriculum, $attachment]), [
            'semester_id' => $semester->id, 'sks' => 4, 'course_type' => 'wajib', 'prerequisite_notes' => 'Diperbaiki',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('4.00', $attachment->fresh()->sks);
        $this->post(route('admin.curricula.submit.store', $curriculum))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        $this->actingAs($head)->post(route('kaprodi.submissions.approve', $curriculum))->assertRedirect();
        $this->assertSame(CurriculumStatus::APPROVED, $curriculum->fresh()->status);
        $this->assertDatabaseCount('curriculum_reviews', 2);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('metrics', ['Menunggu Review' => 0, 'Disetujui' => 1, 'Dipublikasikan' => 0]);
        $this->assertDatabaseHas('curriculum_reviews', ['curriculum_id' => $curriculum->id, 'notes' => 'Perbaiki metadata.']);
        $this->actingAs($admin)->put(route('admin.curricula.update', $curriculum), [])->assertForbidden();
        $this->put(route('admin.curricula.semesters.update', [$curriculum, $semester]), [])->assertForbidden();
        $this->put(route('admin.curricula.courses.update', [$curriculum, $attachment]), [])->assertForbidden();
        $this->post(route('admin.curricula.documents.store', $curriculum), [])->assertForbidden();
        $this->delete(route('admin.curricula.documents.destroy', [$curriculum, $document]))->assertForbidden();
        $this->assertNull($curriculum->fresh()->published_at);
        $this->assertSame('SNAP101', $attachment->fresh()->course_code);
        Storage::disk('documents')->assertExists($document->file_path);
    }
}
