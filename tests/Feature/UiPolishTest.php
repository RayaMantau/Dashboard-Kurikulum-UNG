<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_and_internal_layouts_load_shared_brand_before_surface_styles(): void
    {
        foreach (['home', 'login'] as $route) {
            $this->get(route($route))->assertOk()->assertSeeInOrder(['brand.css', $route === 'home' ? 'public.css' : 'internal.css']);
        }
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]))
            ->get(route('dashboard'))->assertOk()->assertSeeInOrder(['brand.css', 'internal.css']);
        foreach (['brand.css', 'public.css', 'internal.css', 'public.js', 'internal.js'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_destructive_buttons_keep_delete_forms_and_explicit_danger_identity(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 255]);
        $course = Course::factory()->create(['study_program_id' => $program->id]);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->actingAs($user);
        foreach (['semesters', 'courses', 'documents'] as $area) {
            $this->get(route('admin.curricula.'.$area.'.index', $curriculum))->assertOk()
                ->assertSee('danger-button', false)->assertSee('value="DELETE"', false)->assertSee('name="_token"', false);
        }
    }

    public function test_review_actions_are_distinct_and_error_is_connected_to_revision_notes(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED]);
        $url = route('kaprodi.submissions.show', $curriculum);
        $this->actingAs($user)->from($url)->post(route('kaprodi.submissions.revision', $curriculum), [])->assertSessionHasErrors('notes');
        $this->withViewErrors(['notes' => 'Catatan revisi wajib diisi.'])->view('kaprodi.submissions.show', ['curriculum' => $curriculum])->assertSee('success-button', false)->assertSee('warning-button', false)
            ->assertSee('aria-describedby="notes-error"', false)->assertSee('id="notes-error"', false);
    }

    public function test_assignment_validation_messages_have_field_associations(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        Course::factory()->create(['study_program_id' => $program->id]);
        $fields = ['semester_id', 'course_id', 'sks', 'course_type', 'prerequisite_notes'];
        $url = route('admin.curricula.courses.create', $curriculum);
        $this->actingAs($user)->from($url)->post(route('admin.curricula.courses.store', $curriculum), ['prerequisite_notes' => ['invalid']])->assertSessionHasErrors($fields);
        $response = $this->withViewErrors(array_fill_keys($fields, 'Periksa nilai ini.'))->view('admin.curriculum-courses.form', ['assignment' => new CurriculumCourse, 'semesters' => $curriculum->semesters, 'courses' => $program->courses, 'types' => CourseType::cases()]);
        foreach ($fields as $field) {
            $response->assertSee('aria-describedby="'.$field.'-error"', false)->assertSee('id="'.$field.'-error"', false);
        }
    }

    public function test_success_flash_is_semantic_and_escaped(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]))
            ->withSession(['success' => '<script>Success</script>'])->get(route('dashboard'))->assertOk()
            ->assertSee('shell-alert-success', false)->assertSee('role="status"', false)
            ->assertSee('<script>Success</script>')->assertDontSee('<script>Success</script>', false);
    }
}
