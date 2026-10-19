<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumIndexTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $program = StudyProgram::factory()->create(['name' => 'Teknik Informatika']);

        return User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true,
            'study_program_id' => $program->id, 'faculty_id' => $program->faculty_id,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.curricula.index'))->assertRedirect(route('login'));
    }

    public static function otherRoles(): array
    {
        return [[UserRole::UNIVERSITY_ADMIN], [UserRole::FACULTY_ADMIN], [UserRole::HEAD_OF_STUDY_PROGRAM]];
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_enter_management_but_retain_monitoring(UserRole $role): void
    {
        $user = $this->administrator();
        $user->update(['role' => $role]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user)->get(route('admin.curricula.index'))->assertForbidden();
        $this->assertTrue($user->can('view', $curriculum));
        $this->assertFalse($user->can('update', $curriculum));
        $this->get(route('dashboard'))->assertDontSee('href="'.route('admin.curricula.index').'"', false);
    }

    public function test_missing_scope_and_inactive_user_fail_closed_before_validation(): void
    {
        $user = $this->administrator();
        $user->update(['study_program_id' => null]);
        $this->actingAs($user)->get(route('admin.curricula.index', ['status' => 'invalid']))->assertForbidden();
        $user = $this->administrator();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get(route('admin.curricula.index'))->assertForbidden();
    }

    public function test_index_displays_schema_values_context_and_active_navigation_without_mutations(): void
    {
        $user = $this->administrator();
        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $user->study_program_id, 'name' => '<b>Kurikulum Informatika</b>',
            'year' => 2027, 'effective_year' => 2028, 'total_sks' => 145.5, 'total_semesters' => 8,
            'status' => CurriculumStatus::PUBLISHED, 'updated_at' => '2026-09-01 12:30:00',
        ]);
        $before = $curriculum->fresh()->getAttributes();
        $response = $this->actingAs($user)->get(route('admin.curricula.index'))->assertOk()
            ->assertSee('Program Studi Teknik Informatika')->assertSee($curriculum->name)
            ->assertDontSee($curriculum->name, false)->assertSee('2027')->assertSee('2028')
            ->assertSee('145,50')->assertSee('Jumlah Semester')->assertSee('01/09/2026 12:30')
            ->assertSee('status-published', false)->assertSee('Dipublikasikan')
            ->assertSee('href="'.route('admin.curricula.create').'"', false)->assertDontSee('href="'.route('admin.curricula.edit', $curriculum).'"', false);
        $this->assertSame(8, $response->viewData('curricula')->first()->total_semesters);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.curricula.index'), '/').'"\s+aria-current="page"/', $response->getContent());
        $this->assertSame($before, $curriculum->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/kurikulum') && ! str_starts_with($route->getName(), 'admin.curricula.semesters.') && ! str_starts_with($route->getName(), 'admin.curricula.courses.') && ! str_starts_with($route->getName(), 'admin.curricula.documents.')) {
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }

    public static function searches(): array
    {
        return [['Algoritma'], ['2027'], ['  Algoritma  ']];
    }

    #[DataProvider('searches')]
    public function test_grouped_search_and_forged_scope_cannot_leak_other_programs(string $search): void
    {
        $user = $this->administrator();
        $own = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'name' => 'Algoritma Lokal', 'year' => 2027]);
        $other = Curriculum::factory()->create(['name' => 'Algoritma Rahasia', 'year' => 2027]);
        Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'name' => 'Tidak cocok', 'year' => 2020]);
        $response = $this->actingAs($user)->get(route('admin.curricula.index', [
            'search' => $search, 'study_program_id' => $other->study_program_id,
        ]))->assertOk()->assertSee($own->name)->assertDontSee($other->name)->assertDontSee('Tidak cocok');
        $this->assertSame([$own->id], $response->viewData('curricula')->modelKeys());
        $this->get(route('admin.curricula.index', ['search' => 'Rahasia']))->assertOk()
            ->assertDontSee($other->name)->assertViewHas('curricula', fn ($rows): bool => $rows->isEmpty());
    }

    public static function statuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_status_and_year_filters_are_scoped_and_render_central_labels(CurriculumStatus $status): void
    {
        $user = $this->administrator();
        $own = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'status' => $status, 'year' => 2027]);
        Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'status' => $status, 'year' => 2020]);
        Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'status' => $status === CurriculumStatus::DRAFT ? CurriculumStatus::ARCHIVED : CurriculumStatus::DRAFT, 'year' => 2027]);
        $other = Curriculum::factory()->create(['status' => $status, 'year' => 2039, 'name' => 'Kurikulum Prodi Lain']);
        Curriculum::factory()->create(['study_program_id' => $other->study_program_id, 'status' => $status, 'year' => 2027]);
        $this->actingAs($user);
        foreach ([
            ['status' => $status->value],
            ['year' => 2027],
            ['status' => $status->value, 'year' => 2027, 'study_program_id' => $other->study_program_id],
        ] as $filters) {
            $response = $this->get(route('admin.curricula.index', $filters))->assertOk();
            foreach ($response->viewData('curricula') as $record) {
                $this->assertSame($user->study_program_id, $record->study_program_id);
            }
            $this->assertSame([2027, 2020], $response->viewData('years')->all());
        }
        $this->assertSame([$own->id], $response->viewData('curricula')->modelKeys());
        $response->assertSee('status-'.$status->value, false)->assertSee($status->label())->assertDontSee('2039');
    }

    public function test_versions_order_by_year_then_id_descending(): void
    {
        $user = $this->administrator();
        $old = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'year' => 2020]);
        $first = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'year' => 2027]);
        $second = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'year' => 2027]);
        $this->actingAs($user)->get(route('admin.curricula.index'))->assertOk()
            ->assertViewHas('curricula', fn ($rows): bool => $rows->modelKeys() === [$second->id, $first->id, $old->id]);
    }

    public function test_empty_states_and_blank_search(): void
    {
        $user = $this->administrator();
        $this->actingAs($user)->get(route('admin.curricula.index', ['search' => '   ']))->assertOk()
            ->assertSee('Belum ada kurikulum pada Program Studi ini.');
        $this->get(route('admin.curricula.index', ['search' => 'tidak ada']))->assertOk()
            ->assertSee('Tidak ada kurikulum yang sesuai dengan pencarian atau filter.');
    }

    public function test_pagination_preserves_validated_filters_only(): void
    {
        $user = $this->administrator();
        Curriculum::factory()->count(16)->create(['study_program_id' => $user->study_program_id, 'name' => 'Versi Informatika', 'year' => 2027]);
        $filters = ['search' => 'Informatika', 'status' => 'draft', 'year' => 2027, 'study_program_id' => 999];
        $response = $this->actingAs($user)->get(route('admin.curricula.index', $filters))->assertOk();
        $rows = $response->viewData('curricula');
        $this->assertCount(15, $rows);
        $this->assertSame(16, $rows->total());
        parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['search' => 'Informatika', 'status' => 'draft', 'year' => '2027', 'page' => '2'], $query);
        $this->get($rows->nextPageUrl())->assertOk()->assertViewHas('curricula', fn ($rows): bool => $rows->count() === 1);
    }

    public function test_invalid_filters_receive_validation_errors(): void
    {
        $this->actingAs($this->administrator())->from(route('admin.curricula.index'))
            ->get(route('admin.curricula.index', ['status' => 'invalid', 'year' => 'invalid', 'search' => ['bad'], 'page' => 0]))
            ->assertRedirect(route('admin.curricula.index'))->assertSessionHasErrors(['status', 'year', 'search', 'page']);
        $this->get(route('admin.curricula.index', ['year' => 65536]))->assertSessionHasErrors('year');
    }

    public function test_query_count_does_not_grow_with_versions_or_load_course_identity(): void
    {
        $user = $this->administrator();
        Curriculum::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get(route('admin.curricula.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        Curriculum::factory()->count(8)->create(['study_program_id' => $user->study_program_id]);
        $this->actingAs($user->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.curricula.index'))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('curriculum_courses', $query['query']);
            $this->assertStringNotContainsString('from "courses"', $query['query']);
        }
        DB::disableQueryLog();
    }
}
