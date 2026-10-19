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
use App\Services\Curriculum\CurriculumService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CurriculumMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
    }

    private function data(): array
    {
        return ['name' => 'Kurikulum 2027', 'year' => 2027, 'effective_year' => null, 'total_sks' => '144.50', 'total_semesters' => 8, 'description' => 'Deskripsi kurikulum.'];
    }

    private function forged(): array
    {
        return ['study_program_id' => StudyProgram::factory()->create()->id, 'status' => 'published',
            'created_by' => User::factory()->create()->id, 'updated_by' => 999,
            'submitted_at' => '2026-01-01', 'approved_at' => '2026-01-01', 'published_at' => '2026-01-01', 'archived_at' => '2026-01-01'];
    }

    public function test_guest_cannot_access_forms_or_mutations(): void
    {
        $record = Curriculum::factory()->create();
        $this->get(route('admin.curricula.create'))->assertRedirect(route('login'));
        $this->get(route('admin.curricula.edit', $record))->assertRedirect(route('login'));
        $this->post(route('admin.curricula.store'), $this->data())->assertRedirect(route('login'));
        $this->put(route('admin.curricula.update', $record), $this->data())->assertRedirect(route('login'));
    }

    public function test_create_uses_server_identity_defaults_and_audit_without_creating_children(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->get(route('admin.curricula.create'))->assertOk()->assertSee('Tambah Kurikulum')
            ->assertDontSee('name="study_program_id"', false)->assertDontSee('name="status"', false);
        $this->post(route('admin.curricula.store'), [...$this->data(), ...$this->forged()])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Kurikulum berhasil ditambahkan.')
            ->assertRedirect(route('admin.curricula.edit', Curriculum::first()));
        $record = Curriculum::sole();
        $this->assertSame($actor->study_program_id, $record->study_program_id);
        $this->assertSame($actor->id, $record->created_by);
        $this->assertSame($actor->id, $record->updated_by);
        $this->assertSame(CurriculumStatus::DRAFT, $record->status);
        $this->assertSame('144.50', $record->total_sks);
        foreach (['submitted_at', 'approved_at', 'published_at', 'archived_at'] as $field) {
            $this->assertNull($record->{$field});
        }
        $audit = AuditLog::sole();
        $this->assertSame('curriculum.created', $audit->action);
        $this->assertSame(Curriculum::class, $audit->entity_type);
        $this->assertSame($record->id, $audit->entity_id);
        $this->assertNull($audit->old_values);
        $this->assertSame('draft', $audit->new_values['status']);
        foreach (['semesters', 'curriculum_courses', 'documents', 'curriculum_reviews', 'notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->post(route('admin.curricula.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('curricula', 2);
    }

    public static function statuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_policy_locks_forms_and_both_update_methods_and_index_links(CurriculumStatus $status): void
    {
        $actor = $this->actor();
        $record = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id, 'status' => $status]);
        $before = $record->fresh()->getAttributes();
        $editable = in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true);
        $this->actingAs($actor)->get(route('admin.curricula.edit', $record))->assertStatus($editable ? 200 : 403);
        foreach (['put', 'patch'] as $method) {
            $response = $this->{$method}(route('admin.curricula.update', $record), [...$this->data(), ...$this->forged()]);
            if ($editable) {
                $response->assertRedirect(route('admin.curricula.edit', $record))->assertSessionHasNoErrors();
                $updated = $record->fresh();
                $this->assertSame($actor->id, $updated->updated_by);
                $this->assertSame($record->created_by, $updated->created_by);
                $this->assertSame($actor->study_program_id, $updated->study_program_id);
                $this->assertSame($status, $updated->status);
                foreach (['submitted_at', 'approved_at', 'published_at', 'archived_at'] as $field) {
                    $this->assertSame($before[$field], $updated->getRawOriginal($field));
                }
            } else {
                $response->assertForbidden();
                $this->assertSame($before, $record->fresh()->getAttributes());
            }
        }
        $response = $this->get(route('admin.curricula.index'))->assertOk()->assertSee('Tambah Kurikulum');
        $link = 'href="'.route('admin.curricula.edit', $record).'"';
        if ($editable) {
            $response->assertSee($link, false);
            $audit = AuditLog::where('action', 'curriculum.updated')->first();
            $this->assertSame($before['name'], $audit->old_values['name']);
            $this->assertSame($this->data()['name'], $audit->new_values['name']);
        } else {
            $response->assertDontSee($link, false);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public static function unauthorizedRoles(): array
    {
        return [[UserRole::UNIVERSITY_ADMIN], [UserRole::FACULTY_ADMIN], [UserRole::HEAD_OF_STUDY_PROGRAM]];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_cannot_manage_metadata(UserRole $role): void
    {
        $actor = $this->actor();
        $actor->update(['role' => $role]);
        $record = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]);
        $this->actingAs($actor)->get(route('admin.curricula.create'))->assertForbidden();
        $this->get(route('admin.curricula.edit', $record))->assertForbidden();
        $this->post(route('admin.curricula.store'), $this->data())->assertForbidden();
        $this->put(route('admin.curricula.update', $record), $this->data())->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_scope_inactive_and_cross_prodi_are_denied(): void
    {
        $actor = $this->actor();
        $other = Curriculum::factory()->create();
        $this->actingAs($actor)->get(route('admin.curricula.edit', $other))->assertForbidden();
        foreach (['put', 'patch'] as $method) {
            $this->{$method}(route('admin.curricula.update', $other), $this->data())->assertForbidden();
        }
        $actor->update(['study_program_id' => null]);
        $this->get(route('admin.curricula.create'))->assertForbidden();
        $this->post(route('admin.curricula.store'), $this->data())->assertForbidden();
        $actor = $this->actor();
        $actor->update(['is_active' => false]);
        $this->actingAs($actor)->get(route('admin.curricula.create'))->assertForbidden();
        $this->post(route('admin.curricula.store'), $this->data())->assertForbidden();
    }

    public function test_validation_retains_only_editable_old_input(): void
    {
        $this->actingAs($this->actor())->from(route('admin.curricula.create'))
            ->post(route('admin.curricula.store'), [...$this->forged(), 'name' => 'Nama aman'])
            ->assertSessionHasErrors(['year', 'total_sks', 'total_semesters'])
            ->assertSessionHasInput('name', 'Nama aman');
        foreach (array_keys($this->forged()) as $field) {
            $this->assertArrayNotHasKey($field, session('_old_input'));
        }
        $this->followingRedirects()->post(route('admin.curricula.store'), ['name' => 'Nama aman'])->assertOk()->assertSee('Tahun kurikulum wajib diisi.');
        $this->followRedirects = false;
        $this->post(route('admin.curricula.store'), [
            'name' => str_repeat('a', 256), 'year' => 65536, 'effective_year' => 'bad',
            'total_sks' => '1.234', 'total_semesters' => 0, 'description' => str_repeat('x', 10001),
        ])->assertSessionHasErrors(['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description']);
        $this->post(route('admin.curricula.store'), [...$this->data(), 'name' => '', 'total_sks' => -1, 'total_semesters' => 256])
            ->assertSessionHasErrors(['name', 'total_sks', 'total_semesters']);
        $this->assertDatabaseCount('curricula', 0);
    }

    public function test_metadata_update_preserves_snapshots_structure_documents_and_workflow_history(): void
    {
        $actor = $this->actor();
        $record = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id, 'status' => CurriculumStatus::REVISION, 'submitted_at' => now()->subDay()]);
        $semester = Semester::factory()->create(['curriculum_id' => $record->id]);
        $course = Course::factory()->create(['study_program_id' => $actor->study_program_id]);
        CurriculumCourse::factory()->create(['curriculum_id' => $record->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        Document::factory()->create(['curriculum_id' => $record->id]);
        CurriculumReview::create(['curriculum_id' => $record->id, 'reviewer_id' => User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $actor->study_program_id])->id, 'status' => 'revision', 'notes' => 'Perbaiki metadata.', 'reviewed_at' => now()]);
        $course->update(['code' => 'NEW101', 'name' => 'Identitas master baru']);
        $tables = ['semesters', 'curriculum_courses', 'documents', 'curriculum_reviews', 'notifications'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->actingAs($actor)->patch(route('admin.curricula.update', $record), [...$this->data(), ...$this->forged(), 'total_semesters' => 12, 'total_sks' => '160.25'])
            ->assertSessionHasNoErrors();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson());
        }
        $this->assertSame($record->submitted_at->toDateTimeString(), $record->fresh()->submitted_at->toDateTimeString());
        $this->assertSame(12, $record->fresh()->total_semesters);
        $this->assertSame('160.25', $record->fresh()->total_sks);
    }

    public static function operations(): array
    {
        return [['create'], ['update']];
    }

    #[DataProvider('operations')]
    public function test_audit_failure_rolls_back_metadata(string $operation): void
    {
        $actor = $this->actor();
        $record = $operation === 'update' ? Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]) : null;
        $before = $record?->fresh()->getAttributes();
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            app(CurriculumService::class)->saveMetadata($actor, $this->data(), $record);
            $this->fail('Audit failure must roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('curricula', $record ? 1 : 0);
        if ($record) {
            $this->assertSame($before, $record->fresh()->getAttributes());
        }
    }

    public function test_service_rechecks_persisted_status_before_mutation(): void
    {
        $actor = $this->actor();
        $record = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]);
        Curriculum::whereKey($record->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        $this->expectException(AuthorizationException::class);
        app(CurriculumService::class)->saveMetadata($actor, $this->data(), $record);
    }

    public function test_validation_boundaries_and_json_errors(): void
    {
        $this->actingAs($this->actor());
        foreach ([[0, 1, '0'], [65535, 255, '999.99']] as [$year, $semesters, $sks]) {
            $this->post(route('admin.curricula.store'), [...$this->data(), 'year' => $year, 'effective_year' => $year,
                'total_semesters' => $semesters, 'total_sks' => $sks, 'description' => str_repeat('x', 10000)])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('curricula', 2);
        $this->postJson(route('admin.curricula.store'), [...$this->data(), 'total_sks' => '1000', 'year' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['total_sks', 'year']);
    }

    public function test_service_rechecks_actor_scope_and_ignores_untrusted_fields_directly(): void
    {
        $actor = $this->actor();
        $record = app(CurriculumService::class)->saveMetadata($actor, [...$this->data(), ...$this->forged()]);
        $this->assertSame($actor->study_program_id, $record->study_program_id);
        $this->assertSame(CurriculumStatus::DRAFT, $record->status);
        $before = $record->getAttributes();
        User::whereKey($actor->id)->update(['study_program_id' => null]);
        try {
            app(CurriculumService::class)->saveMetadata($actor, $this->data(), $record);
            $this->fail('Missing persisted scope must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
