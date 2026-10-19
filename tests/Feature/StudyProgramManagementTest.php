<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\StudyProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StudyProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(Faculty $faculty): array
    {
        return [
            'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI',
            'degree' => 'S1', 'description' => 'Program komputasi.', 'accreditation' => 'Unggul',
            'website' => 'https://example.org/prodi', 'is_active' => '1',
        ];
    }

    /** @return array<int, array{string, string}> */
    private function endpoints(StudyProgram $program): array
    {
        return [
            ['GET', route('university-admin.study-programs.index')],
            ['GET', route('university-admin.study-programs.create')],
            ['POST', route('university-admin.study-programs.store')],
            ['GET', route('university-admin.study-programs.edit', $program)],
            ['PUT', route('university-admin.study-programs.update', $program)],
            ['PATCH', route('university-admin.study-programs.update', $program)],
        ];
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function deniedUsers(): array
    {
        return [
            'faculty admin' => [UserRole::FACULTY_ADMIN, true],
            'prodi admin' => [UserRole::STUDY_PROGRAM_ADMIN, true],
            'kaprodi' => [UserRole::HEAD_OF_STUDY_PROGRAM, true],
            'inactive university admin' => [UserRole::UNIVERSITY_ADMIN, false],
        ];
    }

    public function test_guest_is_redirected_from_all_endpoints(): void
    {
        $program = StudyProgram::factory()->create();
        foreach ($this->endpoints($program) as [$method, $url]) {
            $this->call($method, $url, $this->payload($program->faculty))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('deniedUsers')]
    public function test_unauthorized_users_cannot_read_or_mutate_master_data(UserRole $role, bool $active): void
    {
        $program = StudyProgram::factory()->create();
        $original = $program->fresh()->getAttributes();
        $user = User::factory()->create([
            'role' => $role, 'is_active' => $active,
            'faculty_id' => $program->faculty_id, 'study_program_id' => $program->id,
        ]);
        $this->actingAs($user);
        foreach ($this->endpoints($program) as [$method, $url]) {
            $this->call($method, $url, $this->payload($program->faculty) + ['role' => 'university_admin'])->assertForbidden();
        }
        $this->assertSame($original, $program->fresh()->getAttributes());
        $this->assertDatabaseCount('study_programs', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_university_admin_can_open_all_pages_with_escaped_data_and_active_navigation(): void
    {
        $program = StudyProgram::factory()->create(['description' => '<script>alert(1)</script>']);
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.study-programs.index'))->assertOk()->assertSee('Manajemen Program Studi')
            ->assertSee($program->name)->assertSee($program->code)->assertSee($program->faculty->name)
            ->assertSee($program->degree)->assertSee($program->accreditation);
        $this->assertStringEndsWith('/program-studi/tambah', route('university-admin.study-programs.create'));
        $this->get(route('university-admin.study-programs.create'))->assertOk()->assertSee('Tambah Program Studi')
            ->assertSee('name="_token"', false)->assertSee('name="degree"', false)->assertSee('name="website"', false);
        $this->get(route('university-admin.study-programs.edit', $program))->assertOk()->assertSee($program->description)
            ->assertDontSee($program->description, false)->assertDontSee('name="slug"', false)
            ->assertSee('value="PUT"', false)->assertDontSee('Hapus Program Studi')
            ->assertSee('href="'.route('university-admin.study-programs.index').'"  aria-current="page"', false);
    }

    public function test_search_matches_name_code_and_faculty_name_and_trims_input(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'Fakultas Teknologi']);
        $match = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF']);
        StudyProgram::factory()->create(['name' => 'Akuntansi', 'code' => 'AK']);
        $this->actingAs($this->administrator());
        foreach ([' Informatika ', ' IF ', ' Teknologi '] as $search) {
            $this->get(route('university-admin.study-programs.index', ['search' => $search]))->assertOk()
                ->assertViewHas('studyPrograms', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $match->id);
        }
        $this->get(route('university-admin.study-programs.index', ['search' => '   ', 'status' => '', 'faculty_id' => '']))
            ->assertOk()->assertViewHas('studyPrograms', fn ($rows): bool => $rows->total() === 2);
    }

    public function test_faculty_and_status_filters_are_combined_in_database(): void
    {
        $faculty = Faculty::factory()->create();
        $active = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => true]);
        $inactive = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false]);
        StudyProgram::factory()->count(2)->create();
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.study-programs.index', ['faculty_id' => $faculty->id]))->assertOk()
            ->assertViewHas('studyPrograms', fn ($rows): bool => $rows->total() === 2);
        foreach (['1' => $active, '0' => $inactive] as $status => $expected) {
            $this->get(route('university-admin.study-programs.index', ['faculty_id' => $faculty->id, 'status' => $status]))->assertOk()
                ->assertViewHas('studyPrograms', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $expected->id);
        }
    }

    public function test_empty_states_and_pagination_preserve_all_filters(): void
    {
        $this->actingAs($this->administrator())->get(route('university-admin.study-programs.index'))->assertOk()->assertSee('Belum ada Program Studi.');
        $faculty = Faculty::factory()->create(['is_active' => false]);
        StudyProgram::factory()->count(17)->create(['faculty_id' => $faculty->id, 'name' => 'Pencarian', 'is_active' => false]);
        StudyProgram::factory()->create();
        $response = $this->get(route('university-admin.study-programs.index', ['search' => 'Pencarian', 'faculty_id' => $faculty->id, 'status' => '0']))->assertOk();
        $rows = $response->viewData('studyPrograms');
        $this->assertCount(15, $rows);
        $this->assertSame(17, $rows->total());
        parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'Pencarian', 'faculty_id' => (string) $faculty->id, 'status' => '0', 'page' => '2'], $query);
        $this->get($rows->nextPageUrl())->assertOk()->assertViewHas('studyPrograms', fn ($rows): bool => $rows->count() === 2 && $rows->total() === 17);
        $this->get(route('university-admin.study-programs.index', ['search' => 'Tidak ditemukan']))->assertOk()
            ->assertSee('Tidak ada Program Studi yang sesuai dengan pencarian atau filter.');
    }

    public function test_create_normalizes_code_saves_all_fields_and_audits(): void
    {
        $faculty = Faculty::factory()->create();
        $user = $this->administrator();
        $this->actingAs($user)->post(route('university-admin.study-programs.store'), [...$this->payload($faculty), 'code' => ' ti '])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $program = StudyProgram::query()->sole();
        $this->assertSame($faculty->id, $program->faculty_id);
        $this->assertSame('TI', $program->code);
        $this->assertSame('teknik-informatika', $program->slug);
        $this->assertSame('S1', $program->degree);
        $this->assertSame('Unggul', $program->accreditation);
        $this->assertSame('https://example.org/prodi', $program->website);
        $this->assertTrue($program->is_active);
        $audit = AuditLog::query()->sole();
        $this->assertSame('study_program.created', $audit->action);
        $this->assertSame(StudyProgram::class, $audit->entity_type);
        $this->assertSame($program->id, $audit->entity_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertSame($faculty->id, $audit->new_values['faculty_id']);
    }

    public function test_create_requires_valid_active_faculty_and_required_fields(): void
    {
        $inactive = Faculty::factory()->create(['is_active' => false]);
        $this->actingAs($this->administrator());
        $this->post(route('university-admin.study-programs.store'), [])->assertSessionHasErrors(['faculty_id', 'name', 'code', 'degree']);
        foreach ([$inactive->id, 99999, ['malformed']] as $facultyId) {
            $this->post(route('university-admin.study-programs.store'), [...$this->payload($inactive), 'faculty_id' => $facultyId])
                ->assertSessionHasErrors('faculty_id');
        }
        $this->assertDatabaseCount('study_programs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_composite_code_uniqueness_allows_same_code_in_different_faculty(): void
    {
        $first = Faculty::factory()->create();
        $second = Faculty::factory()->create();
        $this->actingAs($this->administrator());
        $this->post(route('university-admin.study-programs.store'), $this->payload($first))->assertSessionHasNoErrors();
        $this->post(route('university-admin.study-programs.store'), [...$this->payload($first), 'code' => ' ti '])->assertSessionHasErrors('code');
        $this->post(route('university-admin.study-programs.store'), $this->payload($second))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('study_programs', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_global_slug_collisions_are_suffixed_and_empty_and_long_names_are_safe(): void
    {
        $faculty = Faculty::factory()->create();
        $this->actingAs($this->administrator());
        foreach (['Teknik Informatika', 'Teknik Informatika', 'Teknik Informatika', '!!!', str_repeat('a', 255)] as $index => $name) {
            $this->post(route('university-admin.study-programs.store'), [...$this->payload($faculty), 'name' => $name, 'code' => 'P'.$index])
                ->assertSessionHasNoErrors();
        }
        $slugs = StudyProgram::query()->orderBy('id')->pluck('slug')->all();
        $this->assertSame(['teknik-informatika', 'teknik-informatika-2', 'teknik-informatika-3', 'program-studi'], array_slice($slugs, 0, 4));
        $this->assertLessThanOrEqual(255, strlen($slugs[4]));
    }

    public function test_missing_checkbox_and_nullable_fields_are_supported(): void
    {
        $faculty = Faculty::factory()->create();
        $data = $this->payload($faculty);
        unset($data['is_active'], $data['description'], $data['accreditation'], $data['website']);
        $this->actingAs($this->administrator())->post(route('university-admin.study-programs.store'), $data)->assertSessionHasNoErrors();
        $program = StudyProgram::query()->sole();
        $this->assertFalse($program->is_active);
        $this->assertNull($program->description);
        $this->assertNull($program->accreditation);
        $this->assertNull($program->website);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        return [
            'unsafe website' => ['website', 'javascript:alert(1)'],
            'ftp website' => ['website', 'ftp://example.org'],
            'invalid website' => ['website', 'not-a-url'],
            'long website' => ['website', 'https://example.org/'.str_repeat('a', 255)],
            'long name' => ['name', str_repeat('a', 256)],
            'long code' => ['code', str_repeat('a', 256)],
            'long degree' => ['degree', str_repeat('a', 256)],
            'long accreditation' => ['accreditation', str_repeat('a', 256)],
            'long description' => ['description', str_repeat('a', 10001)],
            'invalid boolean' => ['is_active', 'invalid'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_is_rejected_without_mutation(string $field, mixed $value): void
    {
        $faculty = Faculty::factory()->create();
        $this->actingAs($this->administrator())->post(route('university-admin.study-programs.store'), [...$this->payload($faculty), $field => $value])
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('study_programs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_form_displays_indonesian_validation_and_old_values(): void
    {
        $faculty = Faculty::factory()->create();
        $this->actingAs($this->administrator())->followingRedirects()->from(route('university-admin.study-programs.create'))
            ->post(route('university-admin.study-programs.store'), ['name' => 'Nama Tetap', 'faculty_id' => $faculty->id])
            ->assertOk()->assertSee('Kode Program Studi wajib diisi.')->assertSee('Jenjang wajib diisi.')
            ->assertSee('value="Nama Tetap"', false);
    }

    public function test_update_keeps_own_code_and_slug_and_audits_old_and_new_values(): void
    {
        $program = StudyProgram::factory()->create(['code' => 'TI', 'slug' => 'stable-slug']);
        $oldName = $program->name;
        $this->actingAs($this->administrator())->put(route('university-admin.study-programs.update', $program), [
            ...$this->payload($program->faculty), 'name' => 'Informatika Baru', 'slug' => 'injected',
        ])->assertRedirect(route('university-admin.study-programs.edit', $program))->assertSessionHasNoErrors();
        $this->assertSame('stable-slug', $program->fresh()->slug);
        $this->assertSame('Informatika Baru', $program->fresh()->name);
        $audit = AuditLog::query()->sole();
        $this->assertSame('study_program.updated', $audit->action);
        $this->assertSame($oldName, $audit->old_values['name']);
        $this->assertSame('Informatika Baru', $audit->new_values['name']);
    }

    public function test_faculty_reassignment_checks_target_code_and_active_status(): void
    {
        $program = StudyProgram::factory()->create(['code' => 'TI']);
        $target = Faculty::factory()->create();
        $inactive = Faculty::factory()->create(['is_active' => false]);
        $conflict = StudyProgram::factory()->create(['faculty_id' => $target->id, 'code' => 'TI']);
        $this->actingAs($this->administrator());
        $this->patch(route('university-admin.study-programs.update', $program), [...$this->payload($target), 'id' => $conflict->id])
            ->assertSessionHasErrors('code');
        foreach ([$inactive->id, 99999] as $facultyId) {
            $this->patch(route('university-admin.study-programs.update', $program), [...$this->payload($target), 'faculty_id' => $facultyId])
                ->assertSessionHasErrors('faculty_id');
        }
        $this->assertSame($program->faculty_id, $program->fresh()->faculty_id);
        $this->patch(route('university-admin.study-programs.update', $program), [...$this->payload($target), 'code' => 'NEW'])
            ->assertSessionHasNoErrors();
        $this->assertSame($target->id, $program->fresh()->faculty_id);
        $audit = AuditLog::query()->sole();
        $this->assertSame($program->faculty_id, $audit->old_values['faculty_id']);
        $this->assertSame($target->id, $audit->new_values['faculty_id']);
    }

    public function test_current_inactive_faculty_can_be_retained_and_selectors_exclude_other_inactive_faculties(): void
    {
        $current = Faculty::factory()->create(['is_active' => false]);
        $other = Faculty::factory()->create(['is_active' => false]);
        $active = Faculty::factory()->create();
        $program = StudyProgram::factory()->create(['faculty_id' => $current->id]);
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.study-programs.create'))->assertOk()
            ->assertViewHas('faculties', fn ($rows): bool => $rows->modelKeys() === [$active->id]);
        $response = $this->get(route('university-admin.study-programs.edit', $program))->assertOk()->assertSee('Tidak Aktif - fakultas saat ini');
        $this->assertEqualsCanonicalizing([$current->id, $active->id], $response->viewData('faculties')->modelKeys());
        $this->put(route('university-admin.study-programs.update', $program), $this->payload($current))->assertSessionHasNoErrors();
        $this->assertSame($current->id, $program->fresh()->faculty_id);
        $this->put(route('university-admin.study-programs.update', $program), $this->payload($other))->assertSessionHasErrors('faculty_id');
    }

    public function test_create_form_handles_no_active_faculties(): void
    {
        $this->actingAs($this->administrator())->get(route('university-admin.study-programs.create'))->assertOk()
            ->assertSee('Belum ada fakultas aktif.');
    }

    public function test_status_changes_preserve_all_related_records(): void
    {
        $program = StudyProgram::factory()->create();
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        $course = Course::factory()->create(['study_program_id' => $program->id]);
        $user = User::factory()->create(['study_program_id' => $program->id, 'faculty_id' => $program->faculty_id]);
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $records = collect([$curriculum, $course, $user, $document])->map(fn ($model) => $model->fresh());
        $before = $records->map(fn ($model) => $model->getAttributes())->all();
        $this->actingAs($this->administrator());
        $data = $this->payload($program->faculty);
        unset($data['is_active']);
        $this->patch(route('university-admin.study-programs.update', $program), $data)->assertSessionHasNoErrors();
        $this->assertFalse($program->fresh()->is_active);
        $this->patch(route('university-admin.study-programs.update', $program), [...$data, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($program->fresh()->is_active);
        $this->assertSame($before, $records->map(fn ($model) => $model->fresh()->getAttributes())->all());
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_unexpected_fields_and_delete_requests_cannot_change_protected_data(): void
    {
        $faculty = Faculty::factory()->create();
        $user = $this->administrator();
        $this->actingAs($user);
        $data = [...$this->payload($faculty), 'id' => 999, 'slug' => 'injected', 'role' => 'faculty_admin',
            'study_program_id' => 999, 'created_by' => 999, 'created_at' => '2000-01-01'];
        $this->post(route('university-admin.study-programs.store'), $data)->assertSessionHasNoErrors();
        $program = StudyProgram::query()->sole();
        $this->put(route('university-admin.study-programs.update', $program), $data)->assertSessionHasNoErrors();
        $this->assertSame('teknik-informatika', $program->fresh()->slug);
        $this->assertNotSame(999, $program->id);
        $this->assertNotSame('2000-01-01', $program->fresh()->created_at->toDateString());
        $this->assertSame(UserRole::UNIVERSITY_ADMIN, $user->fresh()->role);
        $this->assertNull($user->fresh()->study_program_id);
        $this->assertNull($user->fresh()->faculty_id);
        $this->delete(route('university-admin.study-programs.update', $program))->assertMethodNotAllowed();
        $this->get(route('university-admin.study-programs.update', [$program, 'is_active' => '0']))->assertMethodNotAllowed();
        $this->assertTrue($program->fresh()->is_active);
    }

    /** @return array<string, array{bool}> */
    public static function mutations(): array
    {
        return ['create' => [false], 'update' => [true]];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_rolls_back_mutation(bool $update): void
    {
        $faculty = Faculty::factory()->create();
        $program = $update ? StudyProgram::factory()->create(['faculty_id' => $faculty->id]) : null;
        $before = $program?->fresh()->getAttributes();
        $this->actingAs($this->administrator())->withoutExceptionHandling();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable');
        });
        try {
            if ($program) {
                $this->put(route('university-admin.study-programs.update', $program), $this->payload($faculty));
            } else {
                $this->post(route('university-admin.study-programs.store'), $this->payload($faculty));
            }
            $this->fail('Audit failure must abort mutation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        if ($program) {
            $this->assertSame($before, $program->fresh()->getAttributes());
        } else {
            $this->assertDatabaseCount('study_programs', 0);
        }
    }

    public function test_sidebar_is_role_specific_and_existing_monitoring_scopes_are_preserved(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => ($role === UserRole::UNIVERSITY_ADMIN ? null : $own->faculty_id), 'study_program_id' => (in_array($role, [UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM], true) ? $own->id : null)]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $link = 'href="'.route('university-admin.study-programs.index').'"';
            if ($role === UserRole::UNIVERSITY_ADMIN) {
                $response->assertSee($link, false);
            } else {
                $response->assertDontSee($link, false);
                $this->assertTrue($user->can('view', $own));
                $this->assertFalse($user->can('view', $other));
                $this->assertFalse($user->can('update', $own));
            }
            if ($role === UserRole::FACULTY_ADMIN) {
                $this->get(route('faculty-admin.study-programs.show', $own))->assertOk();
                $this->get(route('faculty-admin.study-programs.show', $other))->assertForbidden();
            }
        }
    }

    public function test_update_rejects_duplicate_code_in_current_faculty_without_trusting_submitted_id(): void
    {
        $program = StudyProgram::factory()->create(['code' => 'FIRST']);
        $other = StudyProgram::factory()->create(['faculty_id' => $program->faculty_id, 'code' => 'TI']);
        $before = $program->fresh()->getAttributes();
        $this->actingAs($this->administrator())->put(route('university-admin.study-programs.update', $program), [
            ...$this->payload($program->faculty), 'id' => $other->id, 'studyProgram' => $other->id,
        ])->assertSessionHasErrors('code');
        $this->assertSame($before, $program->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_filters_return_validation_errors(): void
    {
        $this->actingAs($this->administrator())->from(route('university-admin.study-programs.index'))
            ->get(route('university-admin.study-programs.index', ['search' => ['invalid'], 'faculty_id' => 99999, 'status' => 'invalid', 'page' => 0]))
            ->assertRedirect(route('university-admin.study-programs.index'))->assertSessionHasErrors(['search', 'faculty_id', 'status', 'page']);
    }

    public function test_service_rechecks_faculty_status_if_changed_after_request_validation(): void
    {
        $faculty = Faculty::factory()->create();
        $data = $this->payload($faculty);
        $faculty->update(['is_active' => false]);
        try {
            app(StudyProgramService::class)->save($this->administrator(), $data);
            $this->fail('An inactive faculty must not receive a new assignment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('faculty_id', $exception->errors());
        }
        $this->assertDatabaseCount('study_programs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_index_query_count_does_not_grow_with_rows_or_faculties(): void
    {
        StudyProgram::factory()->create();
        $this->actingAs($this->administrator());
        DB::enableQueryLog();
        $this->get(route('university-admin.study-programs.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        StudyProgram::factory()->count(8)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('university-admin.study-programs.index'))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
