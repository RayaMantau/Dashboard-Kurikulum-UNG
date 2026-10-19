<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\SemesterService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SemesterManagementTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'total_semesters' => 4]);

        return [$user, $curriculum];
    }

    private function url(string $action, Curriculum $curriculum, ?Semester $semester = null): string
    {
        return route('admin.curricula.semesters.'.$action, $semester ? [$curriculum, $semester] : $curriculum);
    }

    public function test_create_update_delete_and_audit_with_server_owned_identity(): void
    {
        [$user, $curriculum] = $this->context();
        $other = Curriculum::factory()->create();
        $this->actingAs($user)->get($this->url('create', $curriculum))->assertOk()->assertDontSee('name="curriculum_id"', false);
        $this->post($this->url('store', $curriculum), ['semester_number' => 1, 'total_sks' => '21.50', 'curriculum_id' => $other->id, 'name' => 'FAKE'])
            ->assertRedirect($this->url('index', $curriculum))->assertSessionHasNoErrors();
        $semester = Semester::sole();
        $this->assertSame($curriculum->id, $semester->curriculum_id);
        $this->assertSame('Semester 1', $semester->name);
        $this->get($this->url('edit', $curriculum, $semester))->assertOk();
        $this->patch($this->url('update', $curriculum, $semester), ['semester_number' => 4, 'total_sks' => 22, 'curriculum_id' => $other->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Semester 4', $semester->fresh()->name);
        $this->assertSame($curriculum->id, $semester->fresh()->curriculum_id);
        $this->delete($this->url('destroy', $curriculum, $semester))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('semesters', 0);
        $logs = AuditLog::orderBy('id')->get();
        $this->assertSame(['semester.created', 'semester.updated', 'semester.deleted'], $logs->pluck('action')->all());
        $this->assertSame(1, $logs[1]->old_values['semester_number']);
        $this->assertSame(4, $logs[1]->new_values['semester_number']);
        $this->assertNull($logs[2]->new_values);
        $this->assertSame(4, $logs[2]->old_values['semester_number']);
        $this->assertSame(4, $curriculum->fresh()->total_semesters);
    }

    public function test_number_range_uniqueness_and_same_number_in_different_curricula(): void
    {
        [$user, $curriculum] = $this->context();
        $one = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $two = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        $this->actingAs($user);
        foreach ([0, 5, 1] as $number) {
            $this->post($this->url('store', $curriculum), ['semester_number' => $number, 'total_sks' => 20])->assertSessionHasErrors('semester_number');
            $this->put($this->url('update', $curriculum, $two), ['semester_number' => $number, 'total_sks' => 20])->assertSessionHasErrors('semester_number');
        }
        $this->put($this->url('update', $curriculum, $one), ['semester_number' => 1, 'total_sks' => 20])->assertSessionHasNoErrors();
        $another = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->post($this->url('store', $another), ['semester_number' => 1, 'total_sks' => 0])->assertSessionHasNoErrors();
        $this->post($this->url('store', $curriculum), [])->assertSessionHasErrors(['semester_number', 'total_sks']);
        $this->post($this->url('store', $curriculum), ['semester_number' => 3, 'total_sks' => 1000])->assertSessionHasErrors('total_sks');
    }

    public static function statuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_status_matrix_and_read_only_list(CurriculumStatus $status): void
    {
        [$user, $curriculum] = $this->context();
        $curriculum->update(['status' => $status]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $editable = in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true);
        $response = $this->actingAs($user)->get($this->url('index', $curriculum))->assertOk()->assertSee($status->label());
        if ($editable) {
            $response->assertSee('Tambah Semester');
        } else {
            $response->assertDontSee('Tambah Semester')->assertDontSee('Hapus Semester');
        }
        $this->get($this->url('create', $curriculum))->assertStatus($editable ? 200 : 403);
        $this->get($this->url('edit', $curriculum, $semester))->assertStatus($editable ? 200 : 403);
        $this->post($this->url('store', $curriculum), ['semester_number' => 2, 'total_sks' => 20])->assertStatus($editable ? 302 : 403);
        foreach (['put', 'patch'] as $method) {
            $this->{$method}($this->url('update', $curriculum, $semester), ['semester_number' => 1, 'total_sks' => 21])->assertStatus($editable ? 302 : 403);
        }
        $this->delete($this->url('destroy', $curriculum, $semester))->assertStatus($editable ? 302 : 403);
        $this->assertSame($status, $curriculum->fresh()->status);
    }

    public function test_parent_injection_and_other_prodi_are_forbidden(): void
    {
        [$user, $curriculum] = $this->context();
        $sameProdi = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $otherProdi = Curriculum::factory()->create();
        $this->actingAs($user);
        foreach ([$sameProdi, $otherProdi] as $other) {
            $semester = Semester::factory()->create(['curriculum_id' => $other->id]);
            $this->get($this->url('edit', $curriculum, $semester))->assertForbidden();
            $this->put($this->url('update', $curriculum, $semester), ['semester_number' => 1, 'total_sks' => 20])->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $semester))->assertForbidden();
        }
        $this->get($this->url('index', $otherProdi))->assertForbidden();
        $this->get($this->url('create', $otherProdi))->assertForbidden();
        $this->post($this->url('store', $otherProdi), ['semester_number' => 1, 'total_sks' => 20])->assertForbidden();
    }

    public function test_guests_missing_scope_inactive_and_other_roles_cannot_mutate(): void
    {
        [$user, $curriculum] = $this->context();
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->get($this->url('index', $curriculum))->assertRedirect(route('login'));
        $this->post($this->url('store', $curriculum), [])->assertRedirect(route('login'));
        foreach ([
            ['role' => UserRole::UNIVERSITY_ADMIN], ['role' => UserRole::FACULTY_ADMIN], ['role' => UserRole::HEAD_OF_STUDY_PROGRAM],
            ['is_active' => false], ['study_program_id' => null],
        ] as $changes) {
            $actor = User::factory()->create([...['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $user->study_program_id], ...$changes]);
            $this->actingAs($actor)->get($this->url('index', $curriculum))->assertForbidden();
            $this->get($this->url('create', $curriculum))->assertForbidden();
            $this->get($this->url('edit', $curriculum, $semester))->assertForbidden();
            $this->post($this->url('store', $curriculum), [])->assertForbidden();
            $this->put($this->url('update', $curriculum, $semester), [])->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $semester))->assertForbidden();
        }
    }

    public function test_attached_semester_cannot_be_deleted_and_edit_preserves_snapshots(): void
    {
        [$user, $curriculum] = $this->context();
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $attachment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id]);
        $before = $attachment->fresh()->getAttributes();
        $this->actingAs($user)->delete($this->url('destroy', $curriculum, $semester))->assertSessionHasErrors('semester');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->put($this->url('update', $curriculum, $semester), ['semester_number' => 3, 'total_sks' => 25])->assertSessionHasNoErrors();
        $this->assertSame($before, $attachment->fresh()->getAttributes());
        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
    }

    public function test_list_order_and_empty_state(): void
    {
        [$user, $curriculum] = $this->context();
        $this->actingAs($user)->get($this->url('index', $curriculum))->assertOk()->assertSee('Belum ada semester pada kurikulum ini.');
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 4, 'name' => 'Semester Empat']);
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1, 'name' => 'Semester Satu']);
        $this->get($this->url('index', $curriculum))->assertOk()->assertSeeInOrder(['Semester Satu', 'Semester Empat']);
    }

    public static function mutations(): array
    {
        return [['create'], ['update'], ['delete']];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_rolls_back_mutation(string $action): void
    {
        [$user, $curriculum] = $this->context();
        $semester = $action === 'create' ? null : Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $before = $semester?->fresh()->getAttributes();
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $service = app(SemesterService::class);
            if ($action === 'delete') {
                $service->delete($user, $curriculum, $semester);
            } else {
                $service->save($user, $curriculum, ['semester_number' => 2, 'total_sks' => 20], $semester);
            }
            $this->fail('Audit must be atomic.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('semesters', $semester ? 1 : 0);
        if ($semester) {
            $this->assertSame($before, Semester::findOrFail($semester->id)->getAttributes());
        }
    }

    public function test_curriculum_cannot_shrink_below_existing_maximum(): void
    {
        [$user, $curriculum] = $this->context();
        Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 4]);
        $data = ['name' => $curriculum->name, 'year' => 2027, 'total_sks' => 144, 'total_semesters' => 3];
        $this->actingAs($user)->put(route('admin.curricula.update', $curriculum), $data)->assertSessionHasErrors('total_semesters');
        $this->assertSame(4, $curriculum->fresh()->total_semesters);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->put(route('admin.curricula.update', $curriculum), [...$data, 'total_semesters' => 8])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('semesters', 1);
    }

    public function test_service_rechecks_current_status(): void
    {
        [$user, $curriculum] = $this->context();
        Curriculum::whereKey($curriculum->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        $this->expectException(AuthorizationException::class);
        app(SemesterService::class)->save($user, $curriculum, ['semester_number' => 1, 'total_sks' => 20]);
    }
}
