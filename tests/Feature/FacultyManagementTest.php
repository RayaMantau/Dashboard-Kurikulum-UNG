<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FacultyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
    }

    /** @return array{name: string, code: string, description: string, is_active: string} */
    private function payload(): array
    {
        return ['name' => 'Fakultas Teknik', 'code' => 'FT', 'description' => 'Pendidikan teknik.', 'is_active' => '1'];
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function deniedUsers(): array
    {
        return [
            'faculty' => [UserRole::FACULTY_ADMIN, true],
            'prodi' => [UserRole::STUDY_PROGRAM_ADMIN, true],
            'head' => [UserRole::HEAD_OF_STUDY_PROGRAM, true],
            'inactive university' => [UserRole::UNIVERSITY_ADMIN, false],
        ];
    }

    /** @return array<int, array{string, string}> */
    private function endpoints(Faculty $faculty): array
    {
        return [
            ['GET', route('university-admin.faculties.index')],
            ['GET', route('university-admin.faculties.create')],
            ['POST', route('university-admin.faculties.store')],
            ['GET', route('university-admin.faculties.edit', $faculty)],
            ['PUT', route('university-admin.faculties.update', $faculty)],
            ['PATCH', route('university-admin.faculties.update', $faculty)],
        ];
    }

    public function test_guests_are_redirected_from_every_faculty_endpoint(): void
    {
        $faculty = Faculty::factory()->create();
        foreach ($this->endpoints($faculty) as [$method, $url]) {
            $this->call($method, $url, $this->payload())->assertRedirect(route('login'));
        }
    }

    #[DataProvider('deniedUsers')]
    public function test_other_roles_and_inactive_admin_cannot_access_or_mutate_faculties(UserRole $role, bool $active): void
    {
        $faculty = Faculty::factory()->create();
        $original = $faculty->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        foreach ($this->endpoints($faculty) as [$method, $url]) {
            $this->call($method, $url, $this->payload() + ['role' => 'university_admin', 'faculty_id' => $faculty->id])->assertForbidden();
        }
        $this->assertSame($original, $faculty->fresh()->getAttributes());
        $this->assertDatabaseCount('faculties', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_university_admin_can_view_list_create_and_edit_forms(): void
    {
        $faculty = Faculty::factory()->create(['description' => '<script>unsafe()</script>']);
        StudyProgram::factory()->count(3)->create(['faculty_id' => $faculty->id]);
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.faculties.index'))->assertOk()->assertSee('Manajemen Fakultas')
            ->assertSee($faculty->name)->assertSee($faculty->code)
            ->assertViewHas('faculties', fn ($faculties): bool => $faculties->first()->study_programs_count === 3);
        $this->assertStringEndsWith('/fakultas/tambah', route('university-admin.faculties.create'));
        $this->get(route('university-admin.faculties.create'))->assertOk()->assertSee('Tambah Fakultas')->assertSee('name="_token"', false);
        $this->get(route('university-admin.faculties.edit', $faculty))->assertOk()
            ->assertSee($faculty->description)->assertDontSee($faculty->description, false)
            ->assertSee('value="PUT"', false)->assertDontSee('name="slug"', false)->assertDontSee('Hapus Fakultas');
    }

    public function test_index_searches_trimmed_name_and_code_and_filters_both_statuses(): void
    {
        $active = Faculty::factory()->create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
        $inactive = Faculty::factory()->create(['name' => 'Fakultas Ekonomi', 'code' => 'FE', 'is_active' => false]);
        $this->actingAs($this->administrator());
        foreach ([' Teknik ', ' FT '] as $search) {
            $this->get(route('university-admin.faculties.index', ['search' => $search]))->assertOk()
                ->assertViewHas('faculties', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $active->id);
        }
        foreach (['1' => $active, '0' => $inactive] as $status => $faculty) {
            $this->get(route('university-admin.faculties.index', ['status' => $status]))->assertOk()
                ->assertViewHas('faculties', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $faculty->id);
        }
        $this->get(route('university-admin.faculties.index', ['search' => '   ', 'status' => '']))->assertOk()
            ->assertViewHas('faculties', fn ($rows): bool => $rows->total() === 2);
        $this->get(route('university-admin.faculties.index', ['search' => 'FT', 'status' => '0']))->assertOk()->assertSee('Tidak ada fakultas yang sesuai.');
    }

    public function test_empty_state_and_database_pagination_preserve_filters(): void
    {
        $this->actingAs($this->administrator())->get(route('university-admin.faculties.index'))->assertOk()->assertSee('Belum ada data fakultas.');
        Faculty::factory()->count(17)->create(['name' => 'Fakultas Pencarian', 'is_active' => false]);
        Faculty::factory()->create(['name' => 'Fakultas Lain', 'is_active' => true]);
        $response = $this->get(route('university-admin.faculties.index', ['search' => 'Pencarian', 'status' => '0']))->assertOk();
        $faculties = $response->viewData('faculties');
        $this->assertSame(17, $faculties->total());
        $this->assertCount(15, $faculties);
        parse_str(parse_url($faculties->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'Pencarian', 'status' => '0', 'page' => '2'], $query);
        $this->get($faculties->nextPageUrl())->assertOk()->assertViewHas('faculties', fn ($rows): bool => $rows->count() === 2 && $rows->total() === 17);
    }

    public function test_create_generates_slug_normalizes_code_and_records_audit(): void
    {
        $user = $this->administrator();
        $this->actingAs($user)->post(route('university-admin.faculties.store'), [
            ...$this->payload(), 'code' => ' ft ',
        ])->assertRedirect()->assertSessionHas('success');
        $faculty = Faculty::query()->sole();
        $this->assertSame('FT', $faculty->code);
        $this->assertSame('fakultas-teknik', $faculty->slug);
        $this->assertTrue($faculty->is_active);
        $audit = AuditLog::query()->sole();
        $this->assertSame('faculty.created', $audit->action);
        $this->assertSame(Faculty::class, $audit->entity_type);
        $this->assertSame($faculty->id, $audit->entity_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertSame('FT', $audit->new_values['code']);
    }

    public function test_missing_checkbox_is_false_and_invalid_boolean_is_rejected(): void
    {
        $this->actingAs($this->administrator());
        $data = $this->payload();
        unset($data['is_active']);
        $this->post(route('university-admin.faculties.store'), $data)->assertRedirect();
        $this->assertFalse(Faculty::query()->sole()->is_active);
        $this->post(route('university-admin.faculties.store'), [...$this->payload(), 'code' => 'FM', 'is_active' => 'invalid'])
            ->assertSessionHasErrors('is_active');
        $this->assertDatabaseCount('faculties', 1);
    }

    public function test_validation_rejects_missing_required_fields_and_duplicate_code(): void
    {
        Faculty::factory()->create(['code' => 'FT']);
        $this->actingAs($this->administrator());
        $this->from(route('university-admin.faculties.create'))->post(route('university-admin.faculties.store'), [])
            ->assertRedirect(route('university-admin.faculties.create'))
            ->assertSessionHasErrors(['name' => 'Nama fakultas wajib diisi.', 'code' => 'Kode fakultas wajib diisi.']);
        $this->post(route('university-admin.faculties.store'), $this->payload())->assertSessionHasErrors('code');
        $this->assertDatabaseCount('faculties', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_errors_and_old_values_are_visible_in_the_form(): void
    {
        $this->actingAs($this->administrator())->followingRedirects()->from(route('university-admin.faculties.create'))
            ->post(route('university-admin.faculties.store'), ['name' => 'Nama Tetap', 'is_active' => '0'])
            ->assertOk()->assertSee('Kode fakultas wajib diisi.')->assertSee('value="Nama Tetap"', false);
    }

    public function test_duplicate_names_get_sequential_unique_slugs(): void
    {
        $this->actingAs($this->administrator());
        foreach (['FT', 'FT2', 'FT3'] as $code) {
            $this->post(route('university-admin.faculties.store'), [...$this->payload(), 'code' => $code])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(['fakultas-teknik', 'fakultas-teknik-2', 'fakultas-teknik-3'], Faculty::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_generated_slug_fits_column_and_has_fallback_for_empty_transliteration(): void
    {
        $this->actingAs($this->administrator());
        foreach ([str_repeat('a', 255), '!!!'] as $index => $name) {
            $this->post(route('university-admin.faculties.store'), [...$this->payload(), 'name' => $name, 'code' => 'F'.$index])->assertRedirect();
        }
        $this->assertLessThanOrEqual(255, strlen(Faculty::query()->first()->slug));
        $this->assertDatabaseHas('faculties', ['code' => 'F1', 'slug' => 'fakultas']);
    }

    public function test_update_preserves_slug_allows_own_code_and_records_audit(): void
    {
        $faculty = Faculty::factory()->create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'slug' => 'fakultas-teknik']);
        $this->actingAs($this->administrator())->put(route('university-admin.faculties.update', $faculty), [
            ...$this->payload(), 'name' => 'Fakultas Teknik dan Rekayasa', 'slug' => 'attacker-slug',
        ])->assertRedirect(route('university-admin.faculties.edit', $faculty))->assertSessionHas('success');
        $this->assertSame('fakultas-teknik', $faculty->fresh()->slug);
        $this->assertSame('Fakultas Teknik dan Rekayasa', $faculty->fresh()->name);
        $audit = AuditLog::query()->sole();
        $this->assertSame('faculty.updated', $audit->action);
        $this->assertSame('Fakultas Teknik', $audit->old_values['name']);
        $this->assertSame('Fakultas Teknik dan Rekayasa', $audit->new_values['name']);
    }

    public function test_update_rejects_another_facultys_code_even_with_forged_id(): void
    {
        $first = Faculty::factory()->create(['code' => 'FT']);
        $second = Faculty::factory()->create(['code' => 'FE']);
        $this->actingAs($this->administrator())->patch(route('university-admin.faculties.update', $second), [
            ...$this->payload(), 'id' => $first->id, 'faculty_id' => $first->id,
        ])->assertSessionHasErrors('code');
        $this->assertSame('FE', $second->fresh()->code);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deactivation_and_reactivation_do_not_mutate_related_records(): void
    {
        $faculty = Faculty::factory()->create(['code' => 'FT']);
        $program = StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
        $member = User::factory()->create(['faculty_id' => $faculty->id, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        $programBefore = $program->fresh()->getAttributes();
        $memberBefore = $member->fresh()->getAttributes();
        $curriculumBefore = $curriculum->fresh()->getAttributes();
        $this->actingAs($this->administrator());
        foreach (['0', '1'] as $status) {
            $this->patch(route('university-admin.faculties.update', $faculty), [...$this->payload(), 'is_active' => $status])->assertRedirect();
            $this->assertSame($status === '1', $faculty->fresh()->is_active);
        }
        $this->assertSame($programBefore, $program->fresh()->getAttributes());
        $this->assertSame($memberBefore, $member->fresh()->getAttributes());
        $this->assertSame($curriculumBefore, $curriculum->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_unexpected_fields_cannot_change_identity_scope_or_generated_slug(): void
    {
        $user = $this->administrator();
        $this->actingAs($user)->post(route('university-admin.faculties.store'), [
            ...$this->payload(), 'id' => 999, 'slug' => 'forced', 'role' => 'faculty_admin',
            'faculty_id' => 999, 'study_program_id' => 999, 'created_at' => '2000-01-01',
        ])->assertRedirect();
        $faculty = Faculty::query()->sole();
        $this->assertNotSame(999, $faculty->id);
        $this->assertSame('fakultas-teknik', $faculty->slug);
        $this->assertSame(UserRole::UNIVERSITY_ADMIN, $user->fresh()->role);
        $this->assertNull($user->fresh()->faculty_id);
        $this->assertNull($user->fresh()->study_program_id);
        $this->assertNotSame('2000-01-01', $faculty->created_at->toDateString());
    }

    public function test_no_delete_or_state_changing_get_endpoint_exists(): void
    {
        $faculty = Faculty::factory()->create();
        $this->actingAs($this->administrator());
        $this->delete(route('university-admin.faculties.update', $faculty))->assertMethodNotAllowed();
        $this->get(route('university-admin.faculties.update', $faculty), ['is_active' => '0'])->assertMethodNotAllowed();
        $this->get(route('university-admin.faculties.index'))->assertDontSee('Hapus Fakultas');
        $this->assertTrue($faculty->fresh()->is_active);
    }

    public function test_sidebar_link_is_available_only_to_university_admin(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            if ($role === UserRole::UNIVERSITY_ADMIN) {
                $response->assertSee('href="'.route('university-admin.faculties.index').'"', false);
            } else {
                $response->assertDontSee('href="'.route('university-admin.faculties.index').'"', false);
            }
        }
    }

    /** @return array<string, array{bool}> */
    public static function mutations(): array
    {
        return ['create' => [false], 'update' => [true]];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_rolls_back_the_faculty_mutation(bool $update): void
    {
        $faculty = $update ? Faculty::factory()->create(['code' => 'OLD']) : null;
        $original = $faculty?->fresh()->getAttributes();
        $this->actingAs($this->administrator())->withoutExceptionHandling();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable');
        });
        try {
            if ($faculty) {
                $this->put(route('university-admin.faculties.update', $faculty), $this->payload());
            } else {
                $this->post(route('university-admin.faculties.store'), $this->payload());
            }
            $this->fail('Audit failure must abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        if ($faculty) {
            $this->assertSame($original, $faculty->fresh()->getAttributes());
        } else {
            $this->assertDatabaseCount('faculties', 0);
        }
    }

    public function test_index_uses_constant_number_of_queries_as_faculty_count_grows(): void
    {
        Faculty::factory()->create();
        $this->actingAs($this->administrator());
        DB::enableQueryLog();
        $this->get(route('university-admin.faculties.index'))->assertOk();
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (Faculty::factory()->count(8)->create() as $faculty) {
            StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('university-admin.faculties.index'))->assertOk();
        $this->assertSame($first, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
