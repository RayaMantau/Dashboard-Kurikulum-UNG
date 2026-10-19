<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(?StudyProgram $program = null): User
    {
        $program ??= StudyProgram::factory()->create();

        return User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true,
            'study_program_id' => $program->id, 'faculty_id' => $program->faculty_id,
        ]);
    }

    /** @return array{code: string, name: string, description: string} */
    private function payload(): array
    {
        return ['code' => 'IF101', 'name' => 'Algoritma', 'description' => 'Dasar algoritma dan pemrograman.'];
    }

    /** @return array<int, array{string, string}> */
    private function endpoints(Course $course): array
    {
        return [
            ['GET', route('admin.courses.index')],
            ['GET', route('admin.courses.create')],
            ['POST', route('admin.courses.store')],
            ['GET', route('admin.courses.edit', $course)],
            ['PUT', route('admin.courses.update', $course)],
            ['PATCH', route('admin.courses.update', $course)],
        ];
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function deniedUsers(): array
    {
        return [
            'university' => [UserRole::UNIVERSITY_ADMIN, true],
            'faculty' => [UserRole::FACULTY_ADMIN, true],
            'head' => [UserRole::HEAD_OF_STUDY_PROGRAM, true],
            'inactive prodi' => [UserRole::STUDY_PROGRAM_ADMIN, false],
        ];
    }

    public function test_guests_are_redirected_from_all_endpoints(): void
    {
        $course = Course::factory()->create();
        foreach ($this->endpoints($course) as [$method, $url]) {
            $this->call($method, $url, $this->payload())->assertRedirect(route('login'));
        }
    }

    #[DataProvider('deniedUsers')]
    public function test_other_roles_and_inactive_user_cannot_access_management_or_mutate(UserRole $role, bool $active): void
    {
        $course = Course::factory()->create();
        $before = $course->fresh()->getAttributes();
        $user = User::factory()->create([
            'role' => $role, 'is_active' => $active, 'study_program_id' => $course->study_program_id,
            'faculty_id' => $course->studyProgram->faculty_id,
        ]);
        $this->actingAs($user);
        foreach ($this->endpoints($course) as [$method, $url]) {
            $this->call($method, $url, [...$this->payload(), 'role' => 'study_program_admin'])->assertForbidden();
        }
        $this->assertSame($before, $course->fresh()->getAttributes());
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_scope_fails_closed_on_every_endpoint(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => null]);
        $this->actingAs($user);
        foreach ($this->endpoints($course) as [$method, $url]) {
            $this->call($method, $url, [...$this->payload(), 'study_program_id' => $course->study_program_id])->assertForbidden();
        }
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('admin.courses.index').'"', false);
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_index_and_search_by_code_or_name_never_leak_other_programs(): void
    {
        $user = $this->administrator();
        $own = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'IF101', 'name' => 'Algoritma']);
        $other = Course::factory()->create(['code' => 'IF999', 'name' => 'Algoritma Lanjut']);
        $this->actingAs($user);
        foreach (['', '   ', ' Algoritma ', ' IF10 ', 'IF'] as $search) {
            $this->get(route('admin.courses.index', ['search' => $search, 'study_program_id' => $other->study_program_id]))
                ->assertOk()->assertSee('IF101')->assertDontSee('IF999')->assertDontSee('Algoritma Lanjut')
                ->assertSee('Program Studi '.$user->studyProgram->name)
                ->assertViewHas('courses', fn ($courses): bool => $courses->total() === 1 && $courses->first()->id === $own->id);
        }
        $this->get(route('admin.courses.index', ['search' => 'IF999']))->assertOk()
            ->assertSee('Tidak ada mata kuliah yang sesuai dengan pencarian.')->assertDontSee('Algoritma Lanjut');
    }

    public function test_empty_state_and_pagination_preserve_search_and_scope(): void
    {
        $user = $this->administrator();
        Course::factory()->create(['name' => 'Pencarian di Prodi lain']);
        $this->actingAs($user)->get(route('admin.courses.index'))->assertOk()
            ->assertSee('Belum ada mata kuliah pada Program Studi ini.')->assertSee('Tambah Mata Kuliah');
        Course::factory()->count(17)->create(['study_program_id' => $user->study_program_id, 'name' => 'Pencarian']);
        $rows = $this->get(route('admin.courses.index', ['search' => 'Pencarian']))->assertOk()->viewData('courses');
        $this->assertSame(17, $rows->total());
        $this->assertCount(15, $rows);
        parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'Pencarian', 'page' => '2'], $query);
        $this->get($rows->nextPageUrl())->assertOk()->assertDontSee('Pencarian di Prodi lain')
            ->assertViewHas('courses', fn ($courses): bool => $courses->count() === 2 && $courses->total() === 17);
    }

    public function test_forms_show_scope_breadcrumbs_errors_and_no_scope_or_lifecycle_controls(): void
    {
        $user = $this->administrator();
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'description' => '<script>unsafe()</script>']);
        $this->actingAs($user);
        $this->assertStringEndsWith('/mata-kuliah/tambah', route('admin.courses.create'));
        foreach (['create', 'edit'] as $action) {
            $response = $this->get(route('admin.courses.'.$action, $action === 'edit' ? $course : []))->assertOk()
                ->assertSee('Program Studi '.$user->studyProgram->name)->assertSee('Dashboard')
                ->assertSee('name="_token"', false)->assertDontSee('name="study_program_id"', false)
                ->assertDontSee('name="faculty_id"', false)->assertDontSee('name="is_active"', false)
                ->assertDontSee('Hapus Mata Kuliah')->assertDontSee('Nonaktifkan')
                ->assertSee('href="'.route('admin.courses.index').'"  aria-current="page"', false);
            if ($action === 'edit') {
                $response->assertSee('value="PUT"', false)->assertSee($course->description)->assertDontSee($course->description, false);
            }
        }
        $this->followingRedirects()->from(route('admin.courses.create'))->post(route('admin.courses.store'), ['name' => 'Nama Tetap'])
            ->assertOk()->assertSee('Kode mata kuliah wajib diisi.')->assertSee('value="Nama Tetap"', false);
    }

    public function test_create_uses_authenticated_scope_normalizes_code_and_audits(): void
    {
        $user = $this->administrator();
        $other = StudyProgram::factory()->create();
        $this->actingAs($user)->post(route('admin.courses.store'), [
            ...$this->payload(), 'code' => ' if101 ', 'study_program_id' => $other->id,
            'faculty_id' => $other->faculty_id, 'id' => 99999, 'created_at' => '2000-01-01', 'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $course = Course::query()->sole();
        $this->assertSame($user->study_program_id, $course->study_program_id);
        $this->assertSame('IF101', $course->code);
        $this->assertSame('Algoritma', $course->name);
        $this->assertNotSame(99999, $course->id);
        $this->assertNotSame('2000-01-01', $course->created_at->toDateString());
        $audit = AuditLog::query()->sole();
        $this->assertSame('course.created', $audit->action);
        $this->assertSame(Course::class, $audit->entity_type);
        $this->assertSame($course->id, $audit->entity_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertSame($user->study_program_id, $audit->new_values['study_program_id']);
        $this->assertEqualsCanonicalizing(['study_program_id', 'code', 'name', 'description'], array_keys($audit->new_values));
    }

    public function test_code_uniqueness_is_per_program_and_uses_normalized_code(): void
    {
        $first = $this->administrator();
        $second = $this->administrator();
        $this->actingAs($first)->post(route('admin.courses.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('admin.courses.store'), [...$this->payload(), 'code' => ' if101 ', 'study_program_id' => $second->study_program_id])
            ->assertSessionHasErrors('code');
        $this->actingAs($second)->post(route('admin.courses.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('courses', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_required_fields_and_optional_description(): void
    {
        $this->actingAs($this->administrator())->post(route('admin.courses.store'), [])
            ->assertSessionHasErrors(['code' => 'Kode mata kuliah wajib diisi.', 'name' => 'Nama mata kuliah wajib diisi.']);
        $this->assertDatabaseCount('courses', 0);
        $this->post(route('admin.courses.store'), ['code' => 'IF101', 'name' => 'Algoritma'])->assertSessionHasNoErrors();
        $this->assertNull(Course::query()->sole()->description);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'long code' => ['code', str_repeat('a', 256)],
            'long name' => ['name', str_repeat('a', 256)],
            'long description' => ['description', str_repeat('a', 10001)],
            'array code' => ['code', ['invalid']],
            'array name' => ['name', ['invalid']],
            'array description' => ['description', ['invalid']],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_do_not_mutate_database(string $field, mixed $value): void
    {
        $this->actingAs($this->administrator())->post(route('admin.courses.store'), [...$this->payload(), $field => $value])
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_update_preserves_code_and_ownership_despite_forged_scope(): void
    {
        $user = $this->administrator();
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'IF101']);
        $other = Course::factory()->create(['code' => 'IF101']);
        $this->actingAs($user)->put(route('admin.courses.update', $course), [
            ...$this->payload(), 'name' => 'Algoritma Baru', 'study_program_id' => $other->study_program_id,
            'id' => $other->id, 'created_at' => '2000-01-01',
        ])->assertRedirect(route('admin.courses.edit', $course))->assertSessionHasNoErrors()->assertSessionHas('success');
        $fresh = $course->fresh();
        $this->assertSame('Algoritma Baru', $fresh->name);
        $this->assertSame('IF101', $fresh->code);
        $this->assertSame($user->study_program_id, $fresh->study_program_id);
        $this->assertTrue($course->created_at->equalTo($fresh->created_at));
        $audit = AuditLog::query()->sole();
        $this->assertSame('course.updated', $audit->action);
        $this->assertSame($course->name, $audit->old_values['name']);
        $this->assertSame('Algoritma Baru', $audit->new_values['name']);
        $this->assertSame($user->study_program_id, $audit->new_values['study_program_id']);
    }

    public function test_update_cannot_ignore_another_courses_code_using_submitted_id_or_scope(): void
    {
        $user = $this->administrator();
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'FIRST']);
        $conflict = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'IF101']);
        $other = StudyProgram::factory()->create();
        $this->actingAs($user)->patch(route('admin.courses.update', $course), [
            ...$this->payload(), 'id' => $conflict->id, 'course' => $conflict->id, 'study_program_id' => $other->id,
        ])->assertSessionHasErrors('code');
        $this->assertSame('FIRST', $course->fresh()->code);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cross_program_route_binding_denies_edit_put_and_patch_before_validation(): void
    {
        $user = $this->administrator();
        $other = Course::factory()->create();
        $before = $other->fresh()->getAttributes();
        $this->actingAs($user)->get(route('admin.courses.edit', $other))->assertForbidden()->assertDontSee($other->name);
        foreach (['PUT', 'PATCH'] as $method) {
            $this->call($method, route('admin.courses.update', $other), [
                'study_program_id' => $user->study_program_id, 'code' => '', 'name' => '',
            ])->assertForbidden();
        }
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function historicalStatuses(): array
    {
        return ['published' => [CurriculumStatus::PUBLISHED], 'archived' => [CurriculumStatus::ARCHIVED]];
    }

    #[DataProvider('historicalStatuses')]
    public function test_course_metadata_update_preserves_curriculum_structure_and_history(CurriculumStatus $status): void
    {
        $user = $this->administrator();
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'IF101']);
        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $user->study_program_id, 'status' => $status,
            'published_at' => now()->subYear(), 'archived_at' => $status === CurriculumStatus::ARCHIVED ? now() : null,
        ]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $assignment = CurriculumCourse::factory()->create([
            'curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id,
            'sks' => 3, 'course_type' => 'wajib', 'prerequisite_notes' => 'Lulus mata kuliah dasar', 'is_active' => true,
        ]);
        $before = $assignment->fresh()->getAttributes();
        $curriculumBefore = $curriculum->fresh()->getAttributes();
        $semesterBefore = $semester->fresh()->getAttributes();
        $this->actingAs($user)->put(route('admin.courses.update', $course), [
            ...$this->payload(), 'code' => 'IF201', 'name' => 'Nama Diperbarui', 'description' => 'Deskripsi diperbarui.',
            'curriculum_id' => 999, 'semester_id' => 999, 'sks' => 99, 'course_type' => 'pilihan',
            'prerequisite_notes' => 'injected', 'is_active' => false, 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nama Diperbarui', $course->fresh()->name);
        $this->assertSame('IF201', $course->fresh()->code);
        $this->assertSame('IF101', $assignment->fresh()->course_code);
        $this->assertSame($before['course_name'], $assignment->fresh()->course_name);
        $this->assertSame($before, $assignment->fresh()->getAttributes());
        $this->assertSame($curriculumBefore, $curriculum->fresh()->getAttributes());
        $this->assertSame($semesterBefore, $semester->fresh()->getAttributes());
        $this->assertSame($status, $curriculum->fresh()->status);
    }

    public function test_delete_is_not_available_for_unused_or_referenced_courses(): void
    {
        $user = $this->administrator();
        $unused = Course::factory()->create(['study_program_id' => $user->study_program_id]);
        $used = Course::factory()->create(['study_program_id' => $user->study_program_id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        CurriculumCourse::factory()->create(['course_id' => $used->id, 'curriculum_id' => $curriculum->id, 'semester_id' => $semester->id]);
        $this->actingAs($user);
        foreach ([$unused, $used] as $course) {
            $this->delete(route('admin.courses.update', $course))->assertMethodNotAllowed();
            $this->get(route('admin.courses.update', [$course, 'is_active' => '0']))->assertMethodNotAllowed();
        }
        $this->get(route('admin.courses.index'))->assertDontSee('Hapus')->assertDontSee('Nonaktifkan');
        $this->assertDatabaseCount('courses', 2);
        $this->assertDatabaseCount('curriculum_courses', 1);
    }

    public function test_existing_policy_monitoring_scopes_and_sidebar_remain_role_specific(): void
    {
        $own = Course::factory()->create();
        $sameFacultyProgram = StudyProgram::factory()->create(['faculty_id' => $own->studyProgram->faculty_id]);
        $sameFacultyCourse = Course::factory()->create(['study_program_id' => $sameFacultyProgram->id]);
        $other = Course::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create([
                'role' => $role, 'is_active' => true, 'study_program_id' => $own->study_program_id,
                'faculty_id' => $own->studyProgram->faculty_id,
            ]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $link = 'href="'.route('admin.courses.index').'"';
            $this->assertTrue($user->can('view', $own));
            $this->assertSame(in_array($role, [UserRole::UNIVERSITY_ADMIN, UserRole::FACULTY_ADMIN], true), $user->can('view', $sameFacultyCourse));
            $this->assertSame($role === UserRole::UNIVERSITY_ADMIN, $user->can('view', $other));
            $this->assertSame($role === UserRole::STUDY_PROGRAM_ADMIN, $user->can('update', $own));
            if ($role === UserRole::STUDY_PROGRAM_ADMIN) {
                $response->assertSee($link, false);
            } else {
                $response->assertDontSee($link, false);
            }
        }
    }

    /** @return array<string, array{bool}> */
    public static function mutations(): array
    {
        return ['create' => [false], 'update' => [true]];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_rolls_back_course_mutation(bool $update): void
    {
        $user = $this->administrator();
        $course = $update ? Course::factory()->create(['study_program_id' => $user->study_program_id]) : null;
        $before = $course?->fresh()->getAttributes();
        $this->actingAs($user)->withoutExceptionHandling();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable');
        });
        try {
            if ($course) {
                $this->put(route('admin.courses.update', $course), $this->payload());
            } else {
                $this->post(route('admin.courses.store'), $this->payload());
            }
            $this->fail('Audit failure must abort mutation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('courses', $update ? 1 : 0);
        if ($course) {
            $this->assertSame($before, $course->fresh()->getAttributes());
        }
    }

    public function test_service_rechecks_persisted_actor_scope_before_mutating(): void
    {
        $user = $this->administrator();
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id]);
        User::query()->whereKey($user->id)->update(['study_program_id' => null]);
        try {
            app(CourseService::class)->save($user, $this->payload(), $course);
            $this->fail('Stale scope must not authorize a mutation.');
        } catch (AuthorizationException) {
            $this->assertSame($course->name, $course->fresh()->name);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_index_query_count_is_constant_and_invalid_search_is_rejected(): void
    {
        $user = $this->administrator();
        Course::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get(route('admin.courses.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        Course::factory()->count(8)->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.courses.index'))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->from(route('admin.courses.index'))->get(route('admin.courses.index', ['search' => ['invalid'], 'page' => 0]))
            ->assertSessionHasErrors(['search', 'page']);
    }
}
