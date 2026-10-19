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
use App\Services\Curriculum\CurriculumCourseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'total_semesters' => 2]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1, 'name' => 'Semester 1']);
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'IF101', 'name' => 'Algoritma']);

        return [$user, $curriculum, $semester, $course];
    }

    private function url(string $action, Curriculum $curriculum, ?CurriculumCourse $assignment = null): string
    {
        return route('admin.curricula.courses.'.$action, $assignment ? [$curriculum, $assignment] : $curriculum);
    }

    private function data(Semester $semester, Course $course): array
    {
        return ['semester_id' => $semester->id, 'course_id' => $course->id, 'sks' => '3.50', 'course_type' => 'wajib', 'prerequisite_notes' => 'Lulus pengantar'];
    }

    public function test_snapshots_survive_master_change_structural_edit_and_forged_input(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $otherCurriculum = Curriculum::factory()->create();
        $otherCourse = Course::factory()->create();
        $second = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2, 'name' => 'Semester 2']);
        $curriculumBefore = $curriculum->fresh()->getAttributes();
        $semesterBefore = $semester->fresh()->getAttributes();
        $this->actingAs($user)->post($this->url('store', $curriculum), [
            ...$this->data($semester, $course), 'course_code' => 'HACKED', 'course_name' => 'FAKE',
            'curriculum_id' => $otherCurriculum->id, 'is_active' => false,
        ])->assertSessionHasNoErrors()->assertRedirect($this->url('index', $curriculum));
        $assignment = CurriculumCourse::sole();
        $this->assertSame($curriculum->id, $assignment->curriculum_id);
        $this->assertSame('IF101', $assignment->course_code);
        $this->assertSame('Algoritma', $assignment->course_name);
        $this->assertTrue($assignment->is_active);
        $this->delete(route('admin.curricula.semesters.destroy', [$curriculum, $semester]))->assertSessionHasErrors('semester');
        $course->update(['code' => 'IF201', 'name' => 'Dasar Pemrograman']);
        $this->get($this->url('index', $curriculum))->assertOk()->assertSee('IF101')->assertSee('Algoritma')->assertDontSee('IF201')->assertDontSee('Dasar Pemrograman');
        $this->get($this->url('edit', $curriculum, $assignment))->assertOk()->assertSee('IF101')->assertSee('Algoritma')
            ->assertDontSee('name="course_id"', false)->assertDontSee('name="course_code"', false)->assertDontSee('name="is_active"', false)->assertDontSee('Dasar Pemrograman');
        $this->patch($this->url('update', $curriculum, $assignment), [
            ...$this->data($second, $otherCourse), 'sks' => 4, 'course_type' => 'pilihan',
            'course_code' => 'HACKED', 'course_name' => 'HACKED', 'curriculum_id' => $otherCurriculum->id, 'is_active' => false,
        ])->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertSame($course->id, $assignment->course_id);
        $this->assertSame($curriculum->id, $assignment->curriculum_id);
        $this->assertSame('IF101', $assignment->course_code);
        $this->assertSame('Algoritma', $assignment->course_name);
        $this->assertSame($second->id, $assignment->semester_id);
        $this->assertSame('4.00', $assignment->sks);
        $this->assertSame('Pilihan', $assignment->course_type->label());
        $this->assertSame('Lulus pengantar', $assignment->prerequisite_notes);
        $this->assertTrue($assignment->is_active);
        $this->assertSame($curriculumBefore, $curriculum->fresh()->getAttributes());
        $this->assertSame($semesterBefore, $semester->fresh()->getAttributes());
        $this->delete(route('admin.curricula.semesters.destroy', [$curriculum, $semester]))->assertSessionHasNoErrors();
        $audit = AuditLog::where('action', 'curriculum_course.updated')->sole();
        $this->assertSame('IF101', $audit->old_values['course_code']);
        $this->assertSame('IF101', $audit->new_values['course_code']);
        $this->assertSame($semester->id, $audit->old_values['semester_id']);
        $this->assertSame($second->id, $audit->new_values['semester_id']);

        $newVersion = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $newSemester = Semester::factory()->create(['curriculum_id' => $newVersion->id]);
        $this->post($this->url('store', $newVersion), $this->data($newSemester, $course))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('curriculum_courses', ['curriculum_id' => $newVersion->id, 'course_code' => 'IF201', 'course_name' => 'Dasar Pemrograman']);
        $this->assertSame('IF101', $assignment->fresh()->course_code);
    }

    public function test_create_options_and_validation_remain_scoped(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $other = Course::factory()->create(['name' => 'Rahasia Prodi Lain']);
        $foreignSemester = Semester::factory()->create(['name' => 'Semester Rahasia']);
        $this->actingAs($user)->get($this->url('create', $curriculum))->assertOk()->assertSee('Algoritma')
            ->assertDontSee($other->name)->assertDontSee($foreignSemester->name)->assertDontSee('name="curriculum_id"', false);
        foreach ([$other->id, 999999] as $id) {
            $this->post($this->url('store', $curriculum), [...$this->data($semester, $course), 'course_id' => $id])->assertSessionHasErrors('course_id');
        }
        foreach ([$foreignSemester->id, 999999] as $id) {
            $this->post($this->url('store', $curriculum), [...$this->data($semester, $course), 'semester_id' => $id])->assertSessionHasErrors('semester_id');
        }
        $this->assertDatabaseCount('curriculum_courses', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_empty_curriculum_guides_to_semesters_and_existing_inactive_flag_is_preserved(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $empty = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user)->get($this->url('index', $empty))->assertOk()->assertSee('Belum ada semester pada kurikulum ini.');
        $this->get($this->url('create', $empty))->assertOk()->assertSee('Tambahkan semester terlebih dahulu')->assertDontSee('name="course_id"', false);
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id, 'is_active' => false]);
        $this->put($this->url('update', $curriculum, $assignment), [...$this->data($semester, $course), 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertFalse($assignment->fresh()->is_active);
        $this->get($this->url('index', $curriculum))->assertOk()->assertSee($user->studyProgram->name);
    }

    public function test_duplicate_course_in_different_semester_of_same_curriculum_is_rejected(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $next = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        $this->actingAs($user)->post($this->url('store', $curriculum), $this->data($semester, $course))->assertSessionHasNoErrors();
        $this->post($this->url('store', $curriculum), $this->data($next, $course))->assertSessionHasErrors('course_id');
        $this->assertDatabaseCount('curriculum_courses', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_structural_validation_bounds_and_enum(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $this->actingAs($user)->post($this->url('store', $curriculum), [])->assertSessionHasErrors(['semester_id', 'course_id', 'sks', 'course_type']);
        foreach ([-1, 100, '3.001'] as $sks) {
            $this->post($this->url('store', $curriculum), [...$this->data($semester, $course), 'sks' => $sks])->assertSessionHasErrors('sks');
        }
        $this->post($this->url('store', $curriculum), [...$this->data($semester, $course), 'course_type' => 'invalid', 'prerequisite_notes' => str_repeat('x', 10001)])
            ->assertSessionHasErrors(['course_type', 'prerequisite_notes']);
        $this->post($this->url('store', $curriculum), [...$this->data($semester, $course), 'sks' => '99.99', 'prerequisite_notes' => str_repeat('x', 10000)])->assertSessionHasNoErrors();
        $assignment = CurriculumCourse::sole();
        $this->put($this->url('update', $curriculum, $assignment), [...$this->data($semester, $course), 'sks' => 0])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $assignment->fresh()->sks);
    }

    public static function statuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_status_matrix(CurriculumStatus $status): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $newCourse = Course::factory()->create(['study_program_id' => $user->study_program_id]);
        $curriculum->update(['status' => $status]);
        $editable = in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true);
        $response = $this->actingAs($user)->get($this->url('index', $curriculum))->assertOk();
        if ($editable) {
            $response->assertSee('Tambah Mata Kuliah ke Kurikulum');
        } else {
            $response->assertDontSee('Tambah Mata Kuliah ke Kurikulum')->assertDontSee('Hapus dari Kurikulum');
        }
        $this->get($this->url('create', $curriculum))->assertStatus($editable ? 200 : 403);
        $this->get($this->url('edit', $curriculum, $assignment))->assertStatus($editable ? 200 : 403);
        $this->post($this->url('store', $curriculum), $this->data($semester, $newCourse))->assertStatus($editable ? 302 : 403);
        foreach (['put', 'patch'] as $method) {
            $this->{$method}($this->url('update', $curriculum, $assignment), $this->data($semester, $course))->assertStatus($editable ? 302 : 403);
        }
        $this->delete($this->url('destroy', $curriculum, $assignment))->assertStatus($editable ? 302 : 403);
        $this->assertSame($status, $curriculum->fresh()->status);
        if (! $editable) {
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_guests_and_unauthorized_actors(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $this->get($this->url('index', $curriculum))->assertRedirect(route('login'));
        $this->get($this->url('create', $curriculum))->assertRedirect(route('login'));
        $this->post($this->url('store', $curriculum), [])->assertRedirect(route('login'));
        foreach ([
            ['role' => UserRole::UNIVERSITY_ADMIN], ['role' => UserRole::FACULTY_ADMIN], ['role' => UserRole::HEAD_OF_STUDY_PROGRAM],
            ['is_active' => false], ['study_program_id' => null],
        ] as $changes) {
            $actor = User::factory()->create([...['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $user->study_program_id], ...$changes]);
            $this->actingAs($actor)->get($this->url('index', $curriculum))->assertForbidden();
            $this->get($this->url('create', $curriculum))->assertForbidden();
            $this->get($this->url('edit', $curriculum, $assignment))->assertForbidden();
            $this->post($this->url('store', $curriculum), [])->assertForbidden();
            $this->put($this->url('update', $curriculum, $assignment), [])->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $assignment))->assertForbidden();
        }
    }

    public function test_cross_curriculum_assignment_and_semester_injection(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $this->actingAs($user);
        foreach ([$user->study_program_id, StudyProgram::factory()->create()->id] as $programId) {
            $other = Curriculum::factory()->create(['study_program_id' => $programId]);
            $otherSemester = Semester::factory()->create(['curriculum_id' => $other->id]);
            $otherAssignment = CurriculumCourse::factory()->create(['curriculum_id' => $other->id, 'semester_id' => $otherSemester->id]);
            $this->get($this->url('edit', $curriculum, $otherAssignment))->assertForbidden();
            $this->put($this->url('update', $curriculum, $otherAssignment), $this->data($semester, $course))->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $otherAssignment))->assertForbidden();
            $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id]);
            $this->patch($this->url('update', $curriculum, $assignment), $this->data($otherSemester, $course))->assertSessionHasErrors('semester_id');
            $this->assertSame($semester->id, $assignment->fresh()->semester_id);
            if ($programId !== $user->study_program_id) {
                $this->get($this->url('index', $other))->assertForbidden();
                $this->post($this->url('store', $other), $this->data($otherSemester, $course))->assertForbidden();
                $this->get($this->url('edit', $other, $otherAssignment))->assertForbidden();
                $this->delete($this->url('destroy', $other, $otherAssignment))->assertForbidden();
            }
        }
    }

    public function test_delete_removes_only_assignment_and_keeps_metadata_totals(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $before = [$curriculum->fresh()->getAttributes(), $semester->fresh()->getAttributes(), $course->fresh()->getAttributes()];
        $this->actingAs($user)->delete($this->url('destroy', $curriculum, $assignment))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('curriculum_courses', ['id' => $assignment->id]);
        $this->assertSame($before, [$curriculum->fresh()->getAttributes(), $semester->fresh()->getAttributes(), $course->fresh()->getAttributes()]);
        $audit = AuditLog::sole();
        $this->assertSame('curriculum_course.deleted', $audit->action);
        $this->assertSame('IF101', $audit->old_values['course_code']);
        $this->assertNull($audit->new_values);
    }

    public static function operations(): array
    {
        return [['create'], ['update'], ['delete']];
    }

    #[DataProvider('operations')]
    public function test_audit_failure_rolls_back_every_mutation(string $operation): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $assignment = $operation === 'create' ? null : CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $before = $assignment?->fresh()->getAttributes();
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $service = app(CurriculumCourseService::class);
            if ($operation === 'delete') {
                $service->delete($user, $curriculum, $assignment);
            } else {
                $service->save($user, $curriculum, $this->data($semester, $course), $assignment);
            }
            $this->fail('Audit failure must roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('curriculum_courses', $assignment ? 1 : 0);
        if ($assignment) {
            $this->assertSame($before, CurriculumCourse::findOrFail($assignment->id)->getAttributes());
        }
    }

    public function test_empty_semesters_and_snapshot_ordering_with_constant_query_count(): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2, 'name' => 'Semester 2']);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get($this->url('index', $curriculum))->assertOk()->assertSeeInOrder(['Semester 1', 'IF101', 'Semester 2', 'Belum ada mata kuliah.']);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $newCourse = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'AA001']);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $newCourse->id]);
        $newCourse->update(['code' => 'ZZ999']);
        $this->actingAs($user->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($this->url('index', $curriculum))->assertOk()->assertSeeInOrder(['AA001', 'IF101'])->assertDontSee('ZZ999');
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    #[DataProvider('operations')]
    public function test_service_rechecks_fresh_locked_status(string $operation): void
    {
        [$user, $curriculum, $semester, $course] = $this->context();
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        Curriculum::whereKey($curriculum->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        $this->expectException(AuthorizationException::class);
        $service = app(CurriculumCourseService::class);
        if ($operation === 'delete') {
            $service->delete($user, $curriculum, $assignment);
        } else {
            $service->save($user, $curriculum, $this->data($semester, $course), $operation === 'create' ? null : $assignment);
        }
    }
}
