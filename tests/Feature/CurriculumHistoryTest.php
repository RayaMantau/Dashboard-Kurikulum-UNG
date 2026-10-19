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
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumPublicationService;
use App\Services\Curriculum\CurriculumReviewService;
use App\Services\Curriculum\CurriculumService;
use App\Services\Curriculum\CurriculumSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Curriculum} */
    private function context(): array
    {
        $program = StudyProgram::factory()->create();
        $admin = User::factory()->create(['name' => 'Operator Kurikulum', 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id, 'is_active' => true]);
        $head = User::factory()->create(['name' => 'Reviewer Kurikulum', 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id, 'is_active' => true]);
        $curriculum = app(CurriculumService::class)->saveMetadata($admin, ['name' => 'Versi Riwayat', 'year' => 2026, 'total_sks' => 144, 'total_semesters' => 1]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $course = Course::factory()->create(['study_program_id' => $program->id]);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        Document::factory()->create(['curriculum_id' => $curriculum->id, 'uploaded_by' => $admin->id]);

        return [$admin, $head, $curriculum];
    }

    private function publish(User $admin, User $head, Curriculum $curriculum, int $revisions = 0): void
    {
        app(CurriculumSubmissionService::class)->submit($admin, $curriculum);
        for ($number = 1; $number <= $revisions; $number++) {
            app(CurriculumReviewService::class)->requestRevision($head, $curriculum, 'Catatan revisi '.$number);
            app(CurriculumSubmissionService::class)->submit($admin, $curriculum);
        }
        app(CurriculumReviewService::class)->approve($head, $curriculum, 'Catatan persetujuan');
        app(CurriculumPublicationService::class)->publish($head, $curriculum);
    }

    /** @return array<string, array{int}> */
    public static function loops(): array
    {
        return ['basic' => [0], 'revision' => [1], 'multiple revisions' => [2]];
    }

    #[DataProvider('loops')]
    public function test_real_workflow_retains_all_events_notes_and_actors(int $revisions): void
    {
        $this->travelTo(now()->startOfSecond());
        [$admin, $head, $curriculum] = $this->context();
        $this->publish($admin, $head, $curriculum, $revisions);
        $expected = ['Kurikulum dibuat', 'Kurikulum diajukan untuk review'];
        for ($i = 1; $i <= $revisions; $i++) {
            $expected[] = 'Kaprodi meminta revisi';
            $expected[] = 'Kurikulum diajukan ulang untuk review';
        }
        $expected[] = 'Kurikulum disetujui';
        $expected[] = 'Kurikulum dipublikasikan';
        $response = $this->actingAs($admin)->get(route('internal.curricula.history', $curriculum))->assertOk()
            ->assertSee('Catatan persetujuan')->assertSee($admin->name)->assertSee($head->name)
            ->assertSee('Admin Program Studi')->assertSee('Ketua Program Studi');
        $this->assertSame($expected, $response->viewData('events')->pluck('title')->all());
        $this->assertCount($revisions + 1, $response->viewData('reviews'));
        for ($i = 1; $i <= $revisions; $i++) {
            $response->assertSee('Catatan revisi '.$i);
        }
        $this->assertSame([$admin->name, $admin->name], $response->viewData('events')->take(2)->pluck('actor_name')->all());
        $this->assertSame($head->name, $response->viewData('events')->last()['actor_name']);
    }

    public function test_archival_is_part_of_old_version_only(): void
    {
        [$admin, $head, $old] = $this->context();
        $this->publish($admin, $head, $old);
        $new = Curriculum::factory()->create(['study_program_id' => $admin->study_program_id, 'year' => $old->year, 'status' => CurriculumStatus::APPROVED]);
        app(CurriculumPublicationService::class)->publish($head, $new);
        $oldResponse = $this->actingAs($head)->get(route('internal.curricula.history', $old))->assertOk();
        $this->assertSame(['Kurikulum dibuat', 'Kurikulum diajukan untuk review', 'Kurikulum disetujui', 'Kurikulum dipublikasikan', 'Kurikulum diarsipkan'], $oldResponse->viewData('events')->pluck('title')->all());
        $newResponse = $this->get(route('internal.curricula.history', $new))->assertOk();
        $this->assertSame(['Kurikulum dipublikasikan'], $newResponse->viewData('events')->pluck('title')->all());
        $this->assertCount(0, $newResponse->viewData('reviews'));
    }

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [$role->value => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_all_internal_roles_can_read_permitted_scope_and_inactive_users_cannot(UserRole $role): void
    {
        [, , $curriculum] = $this->context();
        $actor = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $curriculum->studyProgram->faculty_id, 'study_program_id' => $curriculum->study_program_id]);
        $this->actingAs($actor)->get(route('internal.curricula.history', $curriculum))->assertOk();
        $actor->update(['is_active' => false]);
        $this->actingAs($actor->fresh())->get(route('internal.curricula.history', $curriculum))->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_scope_is_server_derived_and_management_groups_are_not_broadened(UserRole $role): void
    {
        [, , $curriculum] = $this->context();
        $foreign = StudyProgram::factory()->create();
        $actor = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $foreign->faculty_id, 'study_program_id' => $foreign->id]);
        $this->actingAs($actor);
        $url = route('internal.curricula.history', ['curriculum' => $curriculum, 'faculty_id' => $curriculum->studyProgram->faculty_id, 'study_program_id' => $curriculum->study_program_id]);
        if ($role === UserRole::UNIVERSITY_ADMIN) {
            $this->get($url)->assertOk();
        } else {
            $this->get($url)->assertForbidden()->assertDontSee($curriculum->name);
        }
        $actor->update(['faculty_id' => null, 'study_program_id' => null]);
        $this->actingAs($actor->fresh());
        if ($role === UserRole::UNIVERSITY_ADMIN) {
            $this->get($url)->assertOk();
        } else {
            $this->get($url)->assertForbidden();
        }
        if ($role !== UserRole::STUDY_PROGRAM_ADMIN) {
            $this->get(route('admin.curricula.index'))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_and_non_get_history_operations_are_not_available(): void
    {
        [$admin, , $curriculum] = $this->context();
        $url = route('internal.curricula.history', $curriculum);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($admin)->post($url)->assertStatus(405);
        $this->put($url)->assertStatus(405);
        $this->delete($url)->assertStatus(405);
    }

    public function test_empty_legacy_history_does_not_invent_events_from_status_or_timestamps(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'submitted_at' => now(), 'approved_at' => now(), 'published_at' => now()]);
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $response = $this->actingAs($user)->get(route('internal.curricula.history', $curriculum))->assertOk()
            ->assertSee('Belum ada riwayat aktivitas untuk kurikulum ini.');
        $this->assertCount(0, $response->viewData('events'));
        $this->assertCount(0, $response->viewData('reviews'));
    }

    public function test_exact_entity_filter_privacy_and_legacy_missing_actor(): void
    {
        [$admin, , $curriculum] = $this->context();
        $other = Curriculum::factory()->create(['study_program_id' => $curriculum->study_program_id]);
        foreach ([
            [Course::class, $curriculum->id, 'curriculum.published'],
            [Curriculum::class, $other->id, 'curriculum.published'],
            [Curriculum::class, $curriculum->id, 'user.login'],
            [Curriculum::class, $curriculum->id, 'curriculum.updated'],
        ] as [$entity, $id, $action]) {
            AuditLog::create(['entity_type' => $entity, 'entity_id' => $id, 'action' => $action, 'created_at' => now()]);
        }
        AuditLog::create([
            'entity_type' => Curriculum::class, 'entity_id' => $curriculum->id, 'action' => 'curriculum.submitted', 'user_id' => null,
            'created_at' => now(), 'old_values' => null, 'new_values' => ['file_path' => 'private/SECRET.pdf', 'password' => 'SECRET-HASH'],
            'ip_address' => '192.0.2.99', 'user_agent' => 'SECRET-AGENT',
        ]);
        $response = $this->actingAs($admin)->get(route('internal.curricula.history', $curriculum))->assertOk()->assertSee('Sistem')
            ->assertDontSee('SECRET')->assertDontSee('192.0.2.99')->assertDontSee('file_path')->assertDontSee('old_values')->assertDontSee('new_values');
        $this->assertSame(['Kurikulum dibuat', 'Kurikulum diajukan untuk review'], $response->viewData('events')->pluck('title')->all());
        $this->assertSame(['title', 'occurred_at', 'actor_name', 'actor_role'], array_keys($response->viewData('events')->last()));
    }

    public function test_notes_are_escaped_and_never_matched_to_unrelated_events(): void
    {
        [$admin, $head, $curriculum] = $this->context();
        $other = Curriculum::factory()->create();
        CurriculumReview::create(['curriculum_id' => $other->id, 'reviewer_id' => $head->id, 'status' => 'revision', 'notes' => 'Catatan rahasia versi lain', 'reviewed_at' => now()]);
        CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $head->id, 'status' => 'revision', 'notes' => '<script>alert("catatan")</script>', 'reviewed_at' => null]);
        CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $head->id, 'status' => 'approved', 'notes' => null, 'reviewed_at' => now()]);
        $response = $this->actingAs($admin)->get(route('internal.curricula.history', $curriculum))->assertOk()
            ->assertDontSee('Catatan rahasia versi lain')->assertDontSee('<script>alert', false)->assertSee('&lt;script&gt;', false)
            ->assertSee('Waktu review belum tercatat');
        $this->assertCount(1, $response->viewData('events'));
        $this->assertCount(2, $response->viewData('reviews'));
        $this->assertSame(1, substr_count($response->getContent(), 'Catatan:'));
    }

    public function test_get_is_read_only_and_relationship_queries_do_not_grow_per_actor(): void
    {
        [$admin, $head, $curriculum] = $this->context();
        $this->publish($admin, $head, $curriculum);
        $tables = ['curricula', 'semesters', 'curriculum_courses', 'courses', 'documents', 'curriculum_reviews', 'audit_logs', 'notifications'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin->fresh())->get(route('internal.curricula.history', $curriculum))->assertOk();
        $queries = DB::getQueryLog();
        $this->assertEmpty(array_filter($queries, fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace)\b/i', $query['query']) === 1));
        DB::disableQueryLog();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
        for ($i = 0; $i < 6; $i++) {
            $actor = User::factory()->create();
            AuditLog::create(['entity_type' => Curriculum::class, 'entity_id' => $curriculum->id, 'user_id' => $actor->id, 'action' => 'curriculum.submitted', 'created_at' => now()]);
            CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $actor->id, 'status' => 'revision', 'notes' => 'Catatan tambahan', 'reviewed_at' => now()]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin->fresh())->get(route('internal.curricula.history', $curriculum))->assertOk();
        $this->assertSame(count($queries), count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_navigation_links_to_canonical_history_for_management_review_and_monitoring(): void
    {
        [$admin, $head, $curriculum] = $this->context();
        $link = 'href="'.route('internal.curricula.history', $curriculum).'"';
        $this->actingAs($admin)->get(route('admin.curricula.index'))->assertOk()->assertSee($link, false);
        app(CurriculumSubmissionService::class)->submit($admin, $curriculum);
        $this->actingAs($head)->get(route('kaprodi.submissions.show', $curriculum))->assertOk()->assertSee($link, false);
        app(CurriculumReviewService::class)->approve($head, $curriculum);
        $this->get(route('kaprodi.submissions.approved'))->assertOk()->assertSee($link, false);
        foreach ([UserRole::FACULTY_ADMIN, UserRole::UNIVERSITY_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $curriculum->studyProgram->faculty_id]);
            $route = $role === UserRole::FACULTY_ADMIN ? 'faculty-admin.curricula.show' : 'university-admin.curricula.show';
            $this->actingAs($actor)->get(route($route, $curriculum))->assertOk()->assertSee($link, false);
        }
        $response = $this->get(route('internal.curricula.history', $curriculum))->assertOk();
        $response->assertDontSee('Ajukan Review')->assertDontSee('Konfirmasi Persetujuan')->assertDontSee('data-pending-label="Mempublikasikan', false);
    }

    public function test_event_time_precedes_id_order_and_uses_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Makassar']);
        [$admin, , $curriculum] = $this->context();
        $later = now()->addDays(2)->startOfSecond();
        $earlier = $later->copy()->subDay();
        foreach ([['curriculum.published', $later], ['curriculum.submitted', $earlier]] as [$action, $time]) {
            AuditLog::create(['entity_type' => Curriculum::class, 'entity_id' => $curriculum->id, 'action' => $action, 'created_at' => $time]);
        }
        $response = $this->actingAs($admin)->get(route('internal.curricula.history', $curriculum))->assertOk();
        $this->assertSame(['Kurikulum dibuat', 'Kurikulum diajukan untuk review', 'Kurikulum dipublikasikan'], $response->viewData('events')->pluck('title')->all());
        $this->assertSame($later->copy()->timezone('Asia/Makassar')->format('d/m/Y H:i'), $response->viewData('events')->last()['occurred_at']);
    }
}
