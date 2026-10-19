<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        $cases = [];
        foreach (UserRole::cases() as $role) {
            $cases[$role->value] = [$role];
        }

        return $cases;
    }

    /** @return array<string, UserRole> */
    private function areas(): array
    {
        return [
            'university-admin.dashboard' => UserRole::UNIVERSITY_ADMIN,
            'faculty-admin.dashboard' => UserRole::FACULTY_ADMIN,
            'admin.curricula.index' => UserRole::STUDY_PROGRAM_ADMIN,
            'kaprodi.dashboard' => UserRole::HEAD_OF_STUDY_PROGRAM,
        ];
    }

    private function userFor(UserRole $role, ?StudyProgram $studyProgram = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'faculty_id' => $role === UserRole::UNIVERSITY_ADMIN ? null : $studyProgram?->faculty_id,
            'study_program_id' => in_array($role, [UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM], true) ? $studyProgram?->id : null,
        ]);
    }

    public function test_guests_are_redirected_from_all_internal_areas_and_resources(): void
    {
        foreach (array_keys($this->areas()) as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get('/admin/kurikulum/999')->assertRedirect(route('login'));
        $this->get('/faculty-admin/kurikulum/999')->assertRedirect(route('login'));
        $this->get('/kaprodi/pengajuan/999')->assertRedirect(route('login'));
    }

    #[DataProvider('roles')]
    public function test_each_role_can_enter_only_its_own_area_and_shared_dashboard(UserRole $role): void
    {
        $this->actingAs($this->userFor($role, StudyProgram::factory()->create()));
        $this->get(route('dashboard'))->assertOk();

        foreach ($this->areas() as $routeName => $allowedRole) {
            $this->get(route($routeName, ['role' => $allowedRole->value]))
                ->assertStatus($role === $allowedRole ? 200 : 403);
        }
    }

    #[DataProvider('roles')]
    public function test_deactivation_blocks_an_existing_session_from_all_internal_areas(UserRole $role): void
    {
        $user = $this->userFor($role);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $user->update(['is_active' => false]);
        app('auth')->forgetGuards();

        $this->get(route('dashboard'))->assertForbidden();
        foreach (array_keys($this->areas()) as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }
    }

    public function test_unknown_role_fails_closed_without_enum_exception(): void
    {
        $user = $this->userFor(UserRole::UNIVERSITY_ADMIN);
        DB::table('users')->where('id', $user->id)->update(['role' => 'student']);

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertForbidden();
        $this->get(route('university-admin.dashboard'))->assertForbidden();
    }

    public function test_faculty_monitoring_uses_bound_resource_scope_not_submitted_ids(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create();
        $this->actingAs($this->userFor(UserRole::FACULTY_ADMIN, $own));

        foreach ([$own, $other] as $program) {
            $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
            $status = $program->is($own) ? 200 : 403;
            $this->get(route('faculty-admin.study-programs.show', [
                'studyProgram' => $program->id,
                'faculty_id' => $own->faculty_id,
            ]))->assertStatus($status);
            $this->get(route('faculty-admin.curricula.show', [
                'curriculum' => $curriculum->id,
                'faculty_id' => $own->faculty_id,
                'study_program_id' => $own->id,
            ]))->assertStatus($status);
        }
    }

    public function test_prodi_admin_cannot_view_or_edit_another_prodi_in_the_same_faculty(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id]);
        $this->actingAs($this->userFor(UserRole::STUDY_PROGRAM_ADMIN, $own));

        foreach ([$own, $other] as $program) {
            $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
            foreach (['admin.curricula.show', 'admin.curricula.edit'] as $routeName) {
                $this->get(route($routeName, [
                    'curriculum' => $curriculum->id,
                    'study_program_id' => $own->id,
                    'role' => 'university_admin',
                ]))->assertStatus($program->is($own) ? 200 : 403);
            }
        }
    }

    public function test_edit_and_review_routes_preserve_all_workflow_status_restrictions(): void
    {
        $program = StudyProgram::factory()->create();
        $admin = $this->userFor(UserRole::STUDY_PROGRAM_ADMIN, $program);
        $head = $this->userFor(UserRole::HEAD_OF_STUDY_PROGRAM, $program);

        foreach (CurriculumStatus::cases() as $status) {
            $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status]);
            $this->actingAs($admin)->get(route('admin.curricula.edit', $curriculum))
                ->assertStatus(in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true) ? 200 : 403);
            $this->actingAs($head)->get(route('kaprodi.submissions.show', $curriculum))
                ->assertStatus($status === CurriculumStatus::SUBMITTED ? 200 : 403);
        }
    }

    public function test_head_cannot_review_another_prodi_even_with_forged_scope(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $other->id, 'status' => CurriculumStatus::SUBMITTED]);

        $this->actingAs($this->userFor(UserRole::HEAD_OF_STUDY_PROGRAM, $own))
            ->get(route('kaprodi.submissions.show', ['curriculum' => $curriculum->id, 'study_program_id' => $own->id]))
            ->assertForbidden()->assertDontSee($curriculum->name);
    }

    public function test_university_global_monitoring_does_not_grant_content_editing(): void
    {
        $this->actingAs($this->userFor(UserRole::UNIVERSITY_ADMIN));
        foreach (StudyProgram::factory()->count(2)->create() as $program) {
            $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
            $this->get(route('university-admin.curricula.show', $curriculum))->assertOk();
            $this->get(route('admin.curricula.edit', $curriculum))->assertForbidden();
            $this->get(route('kaprodi.submissions.show', $curriculum))->assertForbidden();
        }
    }

    #[DataProvider('roles')]
    public function test_missing_scope_does_not_grant_access_to_scoped_resources(UserRole $role): void
    {
        $user = $this->userFor($role);
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::SUBMITTED]);
        $routeName = match ($role) {
            UserRole::UNIVERSITY_ADMIN => 'university-admin.curricula.show',
            UserRole::FACULTY_ADMIN => 'faculty-admin.curricula.show',
            UserRole::STUDY_PROGRAM_ADMIN => 'admin.curricula.show',
            UserRole::HEAD_OF_STUDY_PROGRAM => 'kaprodi.submissions.show',
        };
        $this->actingAs($user)->get(route($routeName, $curriculum))
            ->assertStatus($role === UserRole::UNIVERSITY_ADMIN ? 200 : 403);
    }

    public function test_forbidden_page_is_generic_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $this->actingAs($this->userFor(UserRole::FACULTY_ADMIN))
            ->get(route('university-admin.dashboard'))
            ->assertForbidden()->assertSee('Akses ditolak')
            ->assertDontSee('AuthorizationException')->assertDontSee('access-role');
    }

    /** @return array<string, array{string, string, UserRole, CurriculumStatus}> */
    public static function mutations(): array
    {
        return [
            'submit' => ['POST', 'submit', UserRole::STUDY_PROGRAM_ADMIN, CurriculumStatus::DRAFT],
            'put' => ['PUT', 'update', UserRole::STUDY_PROGRAM_ADMIN, CurriculumStatus::DRAFT],
            'patch' => ['PATCH', 'update', UserRole::STUDY_PROGRAM_ADMIN, CurriculumStatus::REVISION],
            'delete' => ['DELETE', 'delete', UserRole::STUDY_PROGRAM_ADMIN, CurriculumStatus::DRAFT],
            'review' => ['POST', 'review', UserRole::HEAD_OF_STUDY_PROGRAM, CurriculumStatus::SUBMITTED],
            'publish' => ['POST', 'publish', UserRole::HEAD_OF_STUDY_PROGRAM, CurriculumStatus::APPROVED],
        ];
    }

    #[DataProvider('mutations')]
    public function test_write_authorization_rejects_wrong_role_scope_and_status(
        string $method, string $ability, UserRole $role, CurriculumStatus $status,
    ): void {
        /** Test-only endpoint exercises the real middleware and policies without implementing CRUD. */
        Route::match([$method], '/authorization-test/{curriculum}', fn (Curriculum $curriculum) => response()->noContent())
            ->middleware(['web', 'auth:web', 'can:access-internal-dashboard', 'can:access-role,"'.$role->value.'"'])
            ->can($ability, 'curriculum');

        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $own->id, 'status' => $status]);
        $foreign = Curriculum::factory()->create(['study_program_id' => $other->id, 'status' => $status]);
        $payload = ['study_program_id' => $own->id, 'faculty_id' => $own->faculty_id, 'role' => $role->value];

        $this->call($method, '/authorization-test/'.$curriculum->id, $payload)->assertRedirect(route('login'));
        foreach (UserRole::cases() as $candidate) {
            $this->actingAs($this->userFor($candidate, $own))
                ->call($method, '/authorization-test/'.$curriculum->id, $payload)
                ->assertStatus($candidate === $role ? 204 : 403);
        }
        $user = $this->userFor($role, $own);
        $this->actingAs($user)->call($method, '/authorization-test/'.$foreign->id, $payload)->assertForbidden();
        $user->update(['is_active' => false]);
        $this->call($method, '/authorization-test/'.$curriculum->id, $payload)->assertForbidden();
        $user->update(['is_active' => true]);
        $curriculum->update(['status' => CurriculumStatus::ARCHIVED]);
        $this->call($method, '/authorization-test/'.$curriculum->id, $payload)->assertForbidden();
        $this->assertSame($other->id, $foreign->fresh()->study_program_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_course_policy_is_discovered_and_enforces_scope_through_middleware(): void
    {
        Route::get('/authorization-course-test/{course}', fn (Course $course) => response()->noContent())
            ->middleware(['web', 'auth:web', 'can:access-internal-dashboard'])->can('view', 'course');

        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create();
        $ownCourse = Course::factory()->create(['study_program_id' => $own->id]);
        $otherCourse = Course::factory()->create(['study_program_id' => $other->id]);

        $this->actingAs($this->userFor(UserRole::FACULTY_ADMIN, $own))
            ->get('/authorization-course-test/'.$ownCourse->id)->assertNoContent();
        $this->get('/authorization-course-test/'.$otherCourse->id)->assertForbidden();
    }
}
