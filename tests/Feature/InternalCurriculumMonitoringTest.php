<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalCurriculumMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_routes_are_authenticated_active_and_read_only(): void
    {
        foreach (['faculty-admin.monitoring.index' => UserRole::FACULTY_ADMIN, 'university-admin.monitoring.index' => UserRole::UNIVERSITY_ADMIN] as $route => $allowed) {
            $this->get(route($route))->assertRedirect(route('login'));
            foreach (UserRole::cases() as $role) {
                $user = User::factory()->create(['is_active' => true, 'role' => $role, 'faculty_id' => Faculty::factory()]);
                $response = $this->actingAs($user)->get(route($route));
                if ($role === $allowed) {
                    $response->assertOk()->assertSee('href="'.route($route).'"  aria-current="page"', false);
                    $this->post(route($route))->assertStatus(405);
                } else {
                    $response->assertForbidden();
                    $this->get(route('dashboard'))->assertDontSee('href="'.route($route).'"', false);
                }
            }
            auth()->forgetGuards();
        }
        $user = User::factory()->create(['is_active' => false, 'role' => UserRole::UNIVERSITY_ADMIN]);
        $this->actingAs($user)->get(route('university-admin.monitoring.index'))->assertForbidden();
    }

    public function test_faculty_scope_ignores_forged_filters_and_hides_private_versions(): void
    {
        $own = StudyProgram::factory()->create(['is_active' => true, 'faculty_id' => Faculty::factory()->create(['is_active' => true])->id]);
        $other = StudyProgram::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['role' => UserRole::FACULTY_ADMIN, 'is_active' => true, 'faculty_id' => $own->faculty_id]);
        $published = Curriculum::factory()->create(['study_program_id' => $own->id, 'status' => CurriculumStatus::PUBLISHED]);
        foreach ([CurriculumStatus::DRAFT, CurriculumStatus::SUBMITTED, CurriculumStatus::APPROVED, CurriculumStatus::REVISION, CurriculumStatus::ARCHIVED] as $status) {
            Curriculum::factory()->create(['study_program_id' => $own->id, 'status' => $status, 'name' => 'Hidden '.$status->value]);
        }
        $response = $this->actingAs($user)->get(route('faculty-admin.monitoring.index', ['faculty_id' => $other->faculty_id, 'study_program_id' => $other->id]))->assertOk()->assertSee($own->name)->assertSee($published->name)->assertDontSee($other->name)->assertDontSee('Hidden ');
        $this->assertSame([$own->id], $response->viewData('programs')->pluck('id')->all());
        $this->get(route('faculty-admin.monitoring.index', ['search' => $other->name]))->assertViewHas('programs', fn ($programs) => $programs->isEmpty());
        $user->update(['faculty_id' => null]);
        $this->get(route('faculty-admin.monitoring.index'))->assertForbidden();
    }

    public function test_university_filters_orders_paginates_and_excludes_inactive_parents(): void
    {
        $a = Faculty::factory()->create(['name' => 'AAA Fakultas', 'is_active' => true]);
        $b = Faculty::factory()->create(['name' => 'BBB Fakultas', 'is_active' => true]);
        $first = StudyProgram::factory()->create(['faculty_id' => $a->id, 'name' => 'Program Awal', 'is_active' => true]);
        $second = StudyProgram::factory()->create(['faculty_id' => $b->id, 'name' => 'Program Kedua', 'is_active' => true]);
        $inactive = StudyProgram::factory()->create(['faculty_id' => $a->id, 'is_active' => false]);
        $inactiveParent = StudyProgram::factory()->create(['faculty_id' => Faculty::factory()->create(['is_active' => false])->id, 'is_active' => true]);
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $this->actingAs($user)->get(route('university-admin.monitoring.index'))->assertOk()->assertSeeInOrder([$first->name, $second->name])->assertDontSee($inactive->name)->assertDontSee($inactiveParent->name);
        $this->get(route('university-admin.monitoring.index', ['faculty_id' => $b->id, 'search' => '  Kedua  ']))->assertOk()->assertSee($second->name)->assertDontSee($first->name);
        StudyProgram::factory()->count(16)->create(['faculty_id' => $b->id, 'name' => 'Program Pagination', 'is_active' => true]);
        $response = $this->get(route('university-admin.monitoring.index', ['faculty_id' => $b->id, 'search' => 'Pagination']))->assertOk();
        $this->assertCount(15, $response->viewData('programs'));
        $this->assertStringContainsString('faculty_id='.$b->id, $response->viewData('programs')->nextPageUrl());
        $this->assertStringContainsString('search=Pagination', $response->viewData('programs')->nextPageUrl());
    }

    public function test_monev_dates_safe_links_and_document_access_reuse_existing_contract(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1));
        $program = StudyProgram::factory()->create(['is_active' => true, 'faculty_id' => Faculty::factory()->create(['is_active' => true])->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED, 'year' => 2024, 'ratified_at' => '2024-06-01', 'external_document_url' => 'https://example.org/kurikulum.pdf']);
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $user = User::factory()->create(['role' => UserRole::FACULTY_ADMIN, 'is_active' => true, 'faculty_id' => $program->faculty_id]);
        $this->actingAs($user)->get(route('faculty-admin.monitoring.index'))->assertOk()->assertSee('2024')->assertSee('1 Juni 2024')->assertSee('Minor: 1 Juni 2026')->assertSee('Rekonstruksi: 1 Juni 2028')->assertSee('Monev Minor')->assertSee('noopener noreferrer')->assertSee('https://example.org/kurikulum.pdf')->assertSee(route('study-programs.curricula.show', [$program->slug, $curriculum->id]))->assertDontSee($document->file_path);
        $curriculum->update(['external_document_url' => 'javascript:alert(1)', 'ratified_at' => null]);
        $this->get(route('faculty-admin.monitoring.index'))->assertOk()->assertDontSee('javascript:')->assertSee('Belum dapat dihitung')->assertSee('Belum ditetapkan');
        $this->get(route('monitoring.index'))->assertOk();
    }

    public function test_invalid_filters_are_rejected_and_empty_state_is_safe(): void
    {
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $this->actingAs($user)->get(route('university-admin.monitoring.index'))->assertOk()->assertSee('Tidak ada Program Studi');
        $this->get(route('university-admin.monitoring.index', ['search' => ['bad'], 'faculty_id' => ['bad']]))->assertSessionHasErrors(['search', 'faculty_id']);
    }
}
