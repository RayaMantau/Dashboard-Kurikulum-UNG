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
use App\Services\Curriculum\CurriculumPublicationService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumPublicationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    /** @return array{User, User, Curriculum} */
    private function context(): array
    {
        $program = StudyProgram::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id, 'is_active' => true]);
        $head = User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id, 'is_active' => true]);
        $target = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::APPROVED, 'approved_at' => now()->subDay(), 'created_by' => $admin->id]);

        return [$admin, $head, $target];
    }

    /** @return array{Semester, CurriculumCourse, Document, CurriculumReview} */
    private function children(Curriculum $curriculum, User $head): array
    {
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $course = Course::factory()->create(['study_program_id' => $curriculum->study_program_id]);
        $attachment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id, 'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf']);
        Storage::disk('documents')->put($document->file_path, '%PDF-1.7 history');
        $review = CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $head->id, 'status' => 'approved', 'notes' => 'Riwayat persetujuan', 'reviewed_at' => now()->subDay()]);
        $course->update(['code' => 'CHANGED'.$course->id, 'name' => 'Master berubah']);

        return [$semester, $attachment, $document, $review];
    }

    public function test_approved_queue_is_scoped_paginated_and_separate_from_review(): void
    {
        [, $head, $target] = $this->context();
        $others = Curriculum::factory()->count(16)->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::APPROVED, 'approved_at' => now()]);
        foreach (CurriculumStatus::cases() as $status) {
            if ($status !== CurriculumStatus::APPROVED) {
                Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => $status]);
            }
        }
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::APPROVED, 'name' => 'Rahasia Prodi Lain']);
        $response = $this->actingAs($head)->get(route('kaprodi.submissions.approved', ['study_program_id' => $foreign->study_program_id]))->assertOk()
            ->assertSee($target->name)->assertSee($head->studyProgram->name)->assertDontSee($foreign->name);
        $this->assertSame(17, $response->viewData('curricula')->total());
        $this->assertCount(15, $response->viewData('curricula')->items());
        $this->get(route('kaprodi.submissions.approved', ['page' => 2]))->assertOk()->assertSee($others->last()->name);
        $this->get(route('kaprodi.submissions.index'))->assertOk()->assertDontSee($target->name);
    }

    public function test_confirmation_previews_only_own_current_publication_and_has_no_side_effects(): void
    {
        [, $head, $target] = $this->context();
        $old = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED]);
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'name' => 'Publikasi Prodi Asing']);
        $before = [$target->fresh()->getAttributes(), $old->fresh()->getAttributes()];
        $this->actingAs($head)->get(route('kaprodi.submissions.publish.confirm', $target))->assertOk()
            ->assertSee($target->name)->assertSee($old->name)->assertDontSee($foreign->name)
            ->assertSee('Diarsipkan')->assertSee('name="_token"', false)
            ->assertSee('method="POST"', false)->assertDontSee('href="'.route('kaprodi.submissions.show', $target).'"', false);
        $this->assertSame($before, [$target->fresh()->getAttributes(), $old->fresh()->getAttributes()]);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_first_publication_ignores_forged_input_and_notifies_only_active_own_admins(): void
    {
        [$admin, $head, $target] = $this->context();
        $second = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $head->study_program_id, 'is_active' => true]);
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $head->study_program_id, 'is_active' => false]);
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => StudyProgram::factory(), 'is_active' => true]);
        $this->actingAs($head)->get(route('kaprodi.submissions.publish.confirm', $target))->assertOk()->assertSee('Belum ada kurikulum aktif yang akan diarsipkan.');
        $approvedAt = $target->approved_at;
        $this->post(route('kaprodi.submissions.publish.store', $target), [
            'status' => 'draft', 'study_program_id' => 999999, 'published_at' => '2000-01-01', 'archived_at' => now()->toDateTimeString(),
            'created_by' => $head->id, 'updated_by' => $head->id, 'previous_curriculum_id' => 999999,
        ])->assertRedirect(route('kaprodi.submissions.approved'))->assertSessionHas('success', 'Kurikulum berhasil dipublikasikan.');
        $target->refresh();
        $this->assertSame(CurriculumStatus::PUBLISHED, $target->status);
        $this->assertTrue($target->published_at->isToday());
        $this->assertTrue($target->approved_at->equalTo($approvedAt));
        $this->assertNull($target->archived_at);
        $this->assertSame($head->study_program_id, $target->study_program_id);
        $this->assertSame($admin->id, $target->created_by);
        $this->assertSame(0, Curriculum::where('status', CurriculumStatus::ARCHIVED)->count());
        $audit = AuditLog::sole();
        $this->assertSame('curriculum.published', $audit->action);
        $this->assertSame('approved', $audit->old_values['status']);
        $this->assertSame('published', $audit->new_values['status']);
        $this->assertSame($head->id, $audit->user_id);
        $this->assertEqualsCanonicalizing([$admin->id, $second->id], Notification::where('type', 'curriculum_published')->pluck('user_id')->all());
        $this->assertDatabaseCount('notifications', 2);
        $this->get(route('kaprodi.submissions.approved'))->assertOk()->assertSee('Tidak ada kurikulum yang menunggu publikasi.');
    }

    public function test_replacement_preserves_all_children_timestamps_and_other_prodi(): void
    {
        [, $head, $target] = $this->context();
        $old = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED, 'published_at' => now()->subYear()]);
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $foreignBefore = $foreign->fresh()->getAttributes();
        $oldPublishedAt = $old->published_at;
        $records = [...$this->children($target, $head), ...$this->children($old, $head)];
        $before = array_map(fn (Model $record): array => $record->fresh()->getAttributes(), $records);
        $this->actingAs($head)->post(route('kaprodi.submissions.publish.store', $target))->assertRedirect();
        $this->assertSame(CurriculumStatus::ARCHIVED, $old->fresh()->status);
        $this->assertTrue($old->fresh()->archived_at->isToday());
        $this->assertTrue($old->fresh()->published_at->equalTo($oldPublishedAt));
        $this->assertSame(CurriculumStatus::PUBLISHED, $target->fresh()->status);
        $this->assertSame(1, Curriculum::where('study_program_id', $head->study_program_id)->where('status', CurriculumStatus::PUBLISHED)->count());
        $this->assertSame($foreignBefore, $foreign->fresh()->getAttributes());
        $this->assertSame($before, array_map(fn (Model $record): array => $record->fresh()->getAttributes(), $records));
        foreach ([$records[2], $records[6]] as $document) {
            $this->assertSame('%PDF-1.7 history', Storage::disk('documents')->get($document->file_path));
        }
        $this->assertSame(['curriculum.archived', 'curriculum.published'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame([$old->id, $target->id], AuditLog::orderBy('id')->pluck('entity_id')->all());
        $this->assertDatabaseCount('curriculum_reviews', 2);
    }

    /** @return array<string, array{UserRole}> */
    public static function forbiddenRoles(): array
    {
        return ['university' => [UserRole::UNIVERSITY_ADMIN], 'faculty' => [UserRole::FACULTY_ADMIN], 'prodi' => [UserRole::STUDY_PROGRAM_ADMIN]];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_cannot_access_publication(UserRole $role): void
    {
        [, $head, $target] = $this->context();
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'study_program_id' => $head->study_program_id]);
        $this->actingAs($user)->get(route('kaprodi.submissions.approved'))->assertForbidden();
        $this->get(route('kaprodi.submissions.publish.confirm', $target))->assertForbidden();
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('kaprodi.submissions.approved').'"', false);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_missing_scope_inactive_and_cross_prodi_fail_closed(): void
    {
        [, $head, $target] = $this->context();
        $this->get(route('kaprodi.submissions.approved'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.submissions.publish.confirm', $target))->assertRedirect(route('login'));
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertRedirect(route('login'));
        foreach ([
            ['study_program_id' => null, 'is_active' => true],
            ['study_program_id' => $target->study_program_id, 'is_active' => false],
        ] as $changes) {
            $head->update($changes);
            $this->actingAs($head->fresh())->get(route('kaprodi.submissions.approved'))->assertForbidden();
            $this->get(route('kaprodi.submissions.publish.confirm', $target))->assertForbidden();
            $this->post(route('kaprodi.submissions.publish.store', $target))->assertForbidden();
        }
        $head->update(['study_program_id' => StudyProgram::factory()->create()->id, 'is_active' => true]);
        $this->actingAs($head->fresh())->get(route('kaprodi.submissions.publish.confirm', $target))->assertForbidden();
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function nonApproved(): array
    {
        return collect(CurriculumStatus::cases())->reject(fn (CurriculumStatus $status): bool => $status === CurriculumStatus::APPROVED)
            ->mapWithKeys(fn (CurriculumStatus $status): array => [$status->value => [$status]])->all();
    }

    #[DataProvider('nonApproved')]
    public function test_only_approved_status_is_publishable(CurriculumStatus $status): void
    {
        [, $head, $target] = $this->context();
        $target->update(['status' => $status]);
        $this->actingAs($head)->get(route('kaprodi.submissions.publish.confirm', $target))->assertForbidden();
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('nonApproved')]
    public function test_service_rejects_stale_approved_instance(CurriculumStatus $status): void
    {
        [, $head, $target] = $this->context();
        Curriculum::whereKey($target->id)->update(['status' => $status]);
        try {
            app(CurriculumPublicationService::class)->publish($head, $target);
            $this->fail('Fresh status must be authoritative.');
        } catch (DomainException) {
            $this->assertSame($status, $target->fresh()->status);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_service_rechecks_actor_and_target_scope(): void
    {
        [, $head, $target] = $this->context();
        $otherProgram = StudyProgram::factory()->create();
        foreach ([['is_active' => false], ['role' => UserRole::UNIVERSITY_ADMIN], ['study_program_id' => null], ['study_program_id' => $otherProgram->id]] as $changes) {
            User::whereKey($head->id)->update(array_merge(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $target->study_program_id], $changes));
            try {
                app(CurriculumPublicationService::class)->publish($head, $target);
                $this->fail('Fresh actor must be authoritative.');
            } catch (DomainException) {
                $this->assertSame(CurriculumStatus::APPROVED, $target->fresh()->status);
            }
        }
        User::whereKey($head->id)->update(['study_program_id' => $head->study_program_id]);
        Curriculum::whereKey($target->id)->update(['study_program_id' => $otherProgram->id]);
        try {
            app(CurriculumPublicationService::class)->publish($head, $target);
            $this->fail('Fresh target scope must be authoritative.');
        } catch (DomainException) {
            $this->assertSame(CurriculumStatus::APPROVED, $target->fresh()->status);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_stale_confirmation_uses_current_published_rows_and_double_publish_has_no_side_effects(): void
    {
        [, $head, $target] = $this->context();
        $old = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED]);
        $replacement = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::APPROVED]);
        $this->actingAs($head)->get(route('kaprodi.submissions.publish.confirm', $target))->assertOk()->assertSee($old->name);
        $secondHead = User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'is_active' => true, 'study_program_id' => $head->study_program_id]);
        app(CurriculumPublicationService::class)->publish($secondHead, $replacement);
        $archivedBefore = $old->fresh()->getAttributes();
        $this->post(route('kaprodi.submissions.publish.store', $target), ['previous_curriculum_id' => $old->id])->assertRedirect();
        $this->assertSame(CurriculumStatus::ARCHIVED, $replacement->fresh()->status);
        $this->assertSame($archivedBefore, $old->fresh()->getAttributes());
        $this->assertSame(1, Curriculum::where('study_program_id', $head->study_program_id)->where('status', CurriculumStatus::PUBLISHED)->count());
        $before = [$target->fresh()->getAttributes(), $replacement->fresh()->getAttributes(), AuditLog::count(), Notification::count()];
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertForbidden();
        try {
            app(CurriculumPublicationService::class)->publish($head, $target);
            $this->fail('Original approved instance must not allow double publication.');
        } catch (DomainException) {
            $this->assertSame($before, [$target->fresh()->getAttributes(), $replacement->fresh()->getAttributes(), AuditLog::count(), Notification::count()]);
        }
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return ['archival audit' => ['curriculum.archived'], 'publication audit' => ['curriculum.published'], 'notification' => ['notification']];
    }

    #[DataProvider('failures')]
    public function test_failure_rolls_back_archival_publication_and_all_side_effects(string $failure): void
    {
        [, $head, $target] = $this->context();
        $old = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED, 'published_at' => now()->subYear()]);
        $before = [$target->fresh()->getAttributes(), $old->fresh()->getAttributes()];
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(function (AuditLog $audit) use ($failure): void {
            if ($audit->action === $failure) {
                throw new RuntimeException('Injected failure');
            }
        });
        Notification::creating(function () use ($failure): void {
            if ($failure === 'notification') {
                throw new RuntimeException('Injected failure');
            }
        });
        try {
            app(CurriculumPublicationService::class)->publish($head, $target);
            $this->fail('Side-effect failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertSame($before, [$target->fresh()->getAttributes(), $old->fresh()->getAttributes()]);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_published_and_archived_records_remain_locked_and_dashboard_updates(): void
    {
        [$admin, $head, $target] = $this->context();
        $old = Curriculum::factory()->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED]);
        $targetChildren = $this->children($target, $head);
        $oldChildren = $this->children($old, $head);
        $this->actingAs($head)->get(route('dashboard'))->assertOk()->assertViewHas('metrics', ['Menunggu Review' => 0, 'Disetujui' => 1, 'Dipublikasikan' => 1])
            ->assertSee('href="'.route('kaprodi.submissions.approved').'"', false);
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertRedirect();
        $response = $this->get(route('dashboard'))->assertOk()->assertViewHas('metrics', ['Menunggu Review' => 0, 'Disetujui' => 0, 'Dipublikasikan' => 1]);
        $this->assertTrue($response->viewData('content')['published']->is($target));
        foreach ([[$target, $targetChildren], [$old, $oldChildren]] as [$curriculum, [$semester, $attachment, $document]]) {
            $this->actingAs($head)->post(route('kaprodi.submissions.approve', $curriculum))->assertForbidden();
            $this->post(route('kaprodi.submissions.publish.store', $curriculum))->assertForbidden();
            $this->actingAs($admin)->put(route('admin.curricula.update', $curriculum), [])->assertForbidden();
            $this->put(route('admin.curricula.semesters.update', [$curriculum, $semester]), [])->assertForbidden();
            $this->put(route('admin.curricula.courses.update', [$curriculum, $attachment]), [])->assertForbidden();
            $this->post(route('admin.curricula.documents.store', $curriculum), [])->assertForbidden();
            $this->delete(route('admin.curricula.documents.destroy', [$curriculum, $document]))->assertForbidden();
            $this->post(route('admin.curricula.submit.store', $curriculum))->assertForbidden();
        }
    }

    public function test_all_existing_published_versions_are_archived_in_id_order(): void
    {
        [, $head, $target] = $this->context();
        $previous = Curriculum::factory()->count(2)->create(['study_program_id' => $head->study_program_id, 'status' => CurriculumStatus::PUBLISHED]);
        $this->actingAs($head)->get(route('kaprodi.submissions.publish.confirm', $target))->assertOk()->assertSee($previous[0]->name)->assertSee($previous[1]->name);
        $this->post(route('kaprodi.submissions.publish.store', $target))->assertRedirect();
        $this->assertSame($previous->modelKeys(), AuditLog::where('action', 'curriculum.archived')->orderBy('id')->pluck('entity_id')->all());
        $this->assertSame(1, Curriculum::where('study_program_id', $head->study_program_id)->where('status', CurriculumStatus::PUBLISHED)->count());
    }

    public function test_domain_rejection_returns_controlled_message_without_mutation(): void
    {
        [, $head, $target] = $this->context();
        $this->mock(CurriculumPublicationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publish')->once()->andThrow(new DomainException('Hanya kurikulum berstatus approved yang dapat dipublikasikan.'));
        });
        $this->actingAs($head)->followingRedirects()->post(route('kaprodi.submissions.publish.store', $target))
            ->assertOk()->assertSee('Hanya kurikulum berstatus approved yang dapat dipublikasikan.');
        $this->assertSame(CurriculumStatus::APPROVED, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
