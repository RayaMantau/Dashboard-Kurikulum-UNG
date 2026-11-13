<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CourseDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(Course $course): User
    {
        return User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $course->study_program_id]);
    }

    public function test_unused_own_course_can_be_deleted_and_audited(): void
    {
        $course = Course::factory()->create();
        $user = $this->administrator($course);
        $this->actingAs($user)->delete(route('admin.courses.destroy', $course), ['study_program_id' => 999])->assertRedirect(route('admin.courses.index'))->assertSessionHas('success');
        $this->assertModelMissing($course);
        $audit = AuditLog::sole();
        $this->assertSame('course.deleted', $audit->action);
        $this->assertSame(Course::class, $audit->entity_type);
        $this->assertSame($course->id, $audit->entity_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($course->code, $audit->old_values['code']);
        $this->assertNull($audit->new_values);
    }

    public function test_guests_other_roles_and_cross_program_users_cannot_delete(): void
    {
        $course = Course::factory()->create();
        $url = route('admin.courses.destroy', $course);
        $this->delete($url)->assertRedirect(route('login'));
        foreach ([UserRole::HEAD_OF_STUDY_PROGRAM, UserRole::FACULTY_ADMIN, UserRole::UNIVERSITY_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'study_program_id' => $course->study_program_id, 'faculty_id' => $course->studyProgram->faculty_id]);
            $this->actingAs($user)->delete($url)->assertForbidden();
            $this->get(route('admin.courses.edit', $course))->assertForbidden()->assertDontSee('Hapus Mata Kuliah');
        }
        $foreign = Course::factory()->create();
        $this->actingAs($this->administrator($foreign))->delete($url, ['study_program_id' => $course->study_program_id])->assertForbidden();
        $user = $this->administrator($course);
        $user->update(['study_program_id' => null]);
        $this->actingAs($user)->delete($url)->assertForbidden();
        $user->update(['study_program_id' => $course->study_program_id, 'is_active' => false]);
        $this->delete($url)->assertForbidden();
        $this->assertModelExists($course);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_every_curriculum_reference_blocks_deletion_and_preserves_snapshots(): void
    {
        foreach (CurriculumStatus::cases() as $status) {
            $course = Course::factory()->create();
            $curriculum = Curriculum::factory()->create(['study_program_id' => $course->study_program_id, 'status' => $status]);
            $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
            $assignment = CurriculumCourse::factory()->create(['course_id' => $course->id, 'curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'is_active' => false]);
            $before = $assignment->fresh()->getAttributes();
            $curriculumBefore = $curriculum->fresh()->getAttributes();
            $this->actingAs($this->administrator($course))->from(route('admin.courses.edit', $course))->delete(route('admin.courses.destroy', $course))
                ->assertRedirect(route('admin.courses.edit', $course))->assertSessionHasErrors(['course' => 'Mata kuliah tidak dapat dihapus karena masih digunakan pada kurikulum.']);
            $this->assertModelExists($course);
            $this->assertSame($before, $assignment->fresh()->getAttributes());
            $this->assertSame($curriculumBefore, $curriculum->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_delete_form_is_only_on_edit_and_uses_confirmation_csrf_and_delete_method(): void
    {
        $course = Course::factory()->create();
        $this->actingAs($this->administrator($course));
        $this->get(route('admin.courses.index'))->assertDontSee('Hapus Mata Kuliah');
        $this->get(route('admin.courses.create'))->assertDontSee('Hapus Mata Kuliah');
        $this->get(route('admin.courses.edit', $course))->assertOk()->assertSee('Zona Berbahaya')->assertSee('Hapus Mata Kuliah')
            ->assertSee('danger-button')->assertSee('name="_token"', false)->assertSee('value="DELETE"', false)->assertSee('type="checkbox" required', false)
            ->assertSee('Simpan Perubahan')->assertSee('Batal');
        $this->assertSame(['DELETE'], Route::getRoutes()->getByName('admin.courses.destroy')->methods());
        $this->get(route('admin.courses.destroy', $course))->assertMethodNotAllowed();
        $this->post(route('admin.courses.destroy', $course))->assertMethodNotAllowed();
    }

    public function test_audit_failure_rolls_back_delete(): void
    {
        $course = Course::factory()->create();
        $user = $this->administrator($course);
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            app(CourseService::class)->delete($user, $course);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertModelExists($course);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
