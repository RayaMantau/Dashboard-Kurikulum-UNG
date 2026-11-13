<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, array $attributes = []): Notification
    {
        return $user->notifications()->create(array_merge(['type' => 'curriculum_submitted', 'title' => 'Pengajuan kurikulum', 'message' => 'Pesan notifikasi'], $attributes));
    }

    public function test_guest_and_inactive_users_cannot_access_or_mutate_notifications(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $notification = $this->notification($user);
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        foreach (['notifications.read' => [$notification->id], 'notifications.read-all' => []] as $route => $parameters) {
            $this->post(route($route, $parameters))->assertRedirect(route('login'));
        }
        $this->actingAs($user)->get(route('notifications.index'))->assertForbidden();
        $this->post(route('notifications.read', $notification->id))->assertForbidden();
        $this->post(route('notifications.read-all'))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_internal_roles_can_access_their_empty_notification_center(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Tidak ada notifikasi.');
        }
    }

    public function test_list_is_owned_ordered_paginated_and_escaped_with_read_states(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]);
        $other = User::factory()->create();
        $this->notification($other, ['title' => 'Rahasia orang lain']);
        $oldest = $this->notification($user, ['title' => 'Notifikasi paling lama', 'created_at' => now()->subDays(5)]);
        for ($i = 0; $i < 14; $i++) {
            $this->notification($user, ['read_at' => now(), 'created_at' => now()->subDay()]);
        }
        $latest = $this->notification($user, ['title' => '<script>judul</script>', 'message' => '<img src=x onerror=alert(1)>']);
        $response = $this->actingAs($user)->get(route('notifications.index', ['user_id' => $other->id]))->assertOk()
            ->assertDontSee('Rahasia orang lain')->assertDontSee($oldest->title)
            ->assertSee($latest->title)->assertDontSee($latest->title, false)
            ->assertSee($latest->message)->assertDontSee($latest->message, false)
            ->assertSee('notification-unread')->assertSee('notification-read')
            ->assertSee('Belum dibaca')->assertSee('Sudah dibaca');
        $this->assertSame($latest->id, $response->viewData('notifications')->first()->id);
        $this->assertSame(15, $response->viewData('notifications')->count());
        $this->get(route('notifications.index', ['page' => 2]))->assertOk()->assertSee($oldest->title);
        $this->assertNull($latest->fresh()->read_at);
    }

    public function test_mark_one_is_owned_idempotent_and_ignores_forged_input(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]);
        $other = User::factory()->create();
        $own = $this->notification($user);
        $foreign = $this->notification($other);
        $this->actingAs($user)->post(route('notifications.read', $foreign->id))->assertNotFound();
        $this->post(route('notifications.read', $own->id), ['user_id' => $other->id, 'read_at' => '2000-01-01', 'title' => 'Forged', 'redirect' => 'https://evil.test'])->assertRedirect(route('notifications.index'));
        $firstRead = $own->fresh()->read_at;
        $this->assertNotNull($firstRead);
        $this->travel(1)->hours();
        $this->post(route('notifications.read', $own->id))->assertRedirect(route('notifications.index'));
        $this->assertTrue($own->fresh()->read_at->equalTo($firstRead));
        $this->assertSame($user->id, $own->fresh()->user_id);
        $this->assertSame('Pengajuan kurikulum', $own->fresh()->title);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_read_all_is_scoped_preserves_old_read_times_and_updates_badge(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]);
        $other = User::factory()->create();
        $own = $this->notification($user);
        $this->notification($user);
        $read = $this->notification($user, ['read_at' => now()->subDay()]);
        $oldTime = $read->read_at;
        $foreign = $this->notification($other);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Notifikasi: 2 belum dibaca')
            ->assertSee('href="'.route('notifications.index').'"', false);
        $this->post(route('notifications.read', $own->id));
        $this->get(route('notifications.index'))->assertSee('Notifikasi: 1 belum dibaca');
        $this->post(route('notifications.read-all'), ['user_id' => $other->id])->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->notifications()->whereNull('read_at')->count());
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertTrue($read->fresh()->read_at->equalTo($oldTime));
        $this->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
        $this->get(route('notifications.index'))->assertSee('Notifikasi: 0 belum dibaca');
    }

    public function test_read_routes_require_post_and_forms_contain_csrf_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]);
        $notification = $this->notification($user);
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('name="_token"', false)
            ->assertSee('method="POST" action="'.route('notifications.read', $notification->id).'"', false);
        foreach (['notifications.read' => [$notification->id], 'notifications.read-all' => []] as $name => $parameters) {
            $this->get(route($name, $parameters))->assertStatus(405);
            $this->assertSame(['POST'], Route::getRoutes()->getByName($name)->methods());
            $this->assertContains('web', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_targets_follow_current_policy_and_status_without_marking_read(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED]);
        $notification = $this->notification($user, ['data' => ['curriculum_id' => $curriculum->id, 'url' => 'https://evil.test']]);
        $this->actingAs($user);
        foreach ([CurriculumStatus::SUBMITTED->value => 'kaprodi.submissions.show', CurriculumStatus::APPROVED->value => 'kaprodi.submissions.publish.confirm', CurriculumStatus::PUBLISHED->value => 'internal.curricula.history'] as $status => $route) {
            $curriculum->update(['status' => $status]);
            $response = $this->get(route('notifications.index'))->assertOk()->assertSee('>Buka</a>', false)->assertDontSee('evil.test');
            $target = $response->viewData('targets')[$notification->id];
            $this->assertSame(route($route, $curriculum), $target);
            $this->get($target)->assertOk();
        }
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_prodi_revision_target_is_edit_and_locked_target_is_history(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::REVISION]);
        $notification = $this->notification($user, ['data' => ['curriculum_id' => $curriculum->id]]);
        $this->actingAs($user)->get(route('notifications.index'))->assertSee(route('admin.curricula.edit', $curriculum));
        $curriculum->update(['status' => CurriculumStatus::SUBMITTED]);
        $response = $this->get(route('notifications.index'))->assertDontSee(route('admin.curricula.edit', $curriculum));
        $this->assertSame(route('internal.curricula.history', $curriculum), $response->viewData('targets')[$notification->id]);
    }

    public function test_missing_malformed_and_foreign_targets_have_no_open_link(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $foreign = Curriculum::factory()->create();
        foreach ([null, ['curriculum_id' => 999999], ['curriculum_id' => ['bad']], ['url' => 'javascript:alert(1)'], ['curriculum_id' => $foreign->id]] as $data) {
            $this->notification($user, ['data' => $data]);
        }
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertDontSee('>Buka</a>', false)->assertViewHas('targets', []);
    }

    public function test_university_and_faculty_targets_respect_monitoring_scope(): void
    {
        $curriculum = Curriculum::factory()->create();
        foreach ([UserRole::UNIVERSITY_ADMIN, UserRole::FACULTY_ADMIN] as $role) {
            $user = User::factory()->create(['is_active' => true, 'role' => $role, 'faculty_id' => $curriculum->studyProgram->faculty_id]);
            $notification = $this->notification($user, ['data' => ['curriculum_id' => $curriculum->id]]);
            $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
            $this->assertSame(route('internal.curricula.history', $curriculum), $response->viewData('targets')[$notification->id]);
            if ($role === UserRole::FACULTY_ADMIN) {
                $user->update(['faculty_id' => null]);
                $this->get(route('notifications.index'))->assertViewHas('targets', []);
            }
        }
    }
}
