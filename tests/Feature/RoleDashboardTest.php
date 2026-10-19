<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumReview;
use App\Models\Document;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        $roles = [];
        foreach (UserRole::cases() as $role) {
            $roles[$role->value] = [$role];
        }

        return $roles;
    }

    private function userFor(UserRole $role, ?StudyProgram $program = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'faculty_id' => $program?->faculty_id,
            'study_program_id' => $program?->id,
        ]);
    }

    public function test_guest_and_inactive_user_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $user = $this->userFor(UserRole::UNIVERSITY_ADMIN);
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_active_user_gets_role_dashboard_with_empty_states(UserRole $role): void
    {
        $program = StudyProgram::factory()->create();
        $response = $this->actingAs($this->userFor($role, $program))->get(route('dashboard'));
        $response->assertOk()->assertSee('Dashboard '.$role->label())
            ->assertViewHas('statusCounts', fn (array $counts): bool => array_sum($counts) === 0);
        match ($role) {
            UserRole::UNIVERSITY_ADMIN => $response->assertSee('Belum ada aktivitas terbaru.'),
            UserRole::FACULTY_ADMIN, UserRole::STUDY_PROGRAM_ADMIN => $response->assertSee('Belum ada kurikulum yang dipublikasikan.'),
            UserRole::HEAD_OF_STUDY_PROGRAM => $response->assertSee('Tidak ada kurikulum yang menunggu review.')->assertSee('Belum ada riwayat review.'),
        };
    }

    public function test_university_dashboard_counts_all_faculties_and_only_active_internal_users(): void
    {
        $user = $this->userFor(UserRole::UNIVERSITY_ADMIN);
        $first = StudyProgram::factory()->create();
        $second = StudyProgram::factory()->create();
        Curriculum::factory()->create(['study_program_id' => $first->id, 'created_by' => $user->id, 'status' => CurriculumStatus::PUBLISHED]);
        Curriculum::factory()->create(['study_program_id' => $second->id, 'created_by' => $user->id, 'status' => CurriculumStatus::SUBMITTED]);
        $this->userFor(UserRole::FACULTY_ADMIN, $first);
        User::factory()->create(['is_active' => false]);
        $outsider = User::factory()->create();
        DB::table('users')->where('id', $outsider->id)->update(['role' => 'student']);

        $this->actingAs($user)->get(route('dashboard', ['faculty_id' => $first->faculty_id]))
            ->assertOk()->assertViewHas('metrics', [
                'Total Fakultas' => 2,
                'Total Program Studi' => 2,
                'Total Kurikulum' => 2,
                'Dipublikasikan' => 1,
                'Menunggu Review' => 1,
                'Pengguna Internal Aktif' => 2,
            ]);
        $this->get(route('admin.curricula.index'))->assertForbidden();
    }

    public function test_university_activity_is_limited_ordered_and_excludes_authentication_details(): void
    {
        $user = $this->userFor(UserRole::UNIVERSITY_ADMIN);
        foreach (range(1, 7) as $number) {
            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => Curriculum::class,
                'action' => 'curriculum.submitted',
                'created_at' => now()->subMinutes(8 - $number),
                'new_values' => ['secret' => 'Private snapshot'],
                'ip_address' => '192.0.2.1',
            ]);
        }
        AuditLog::create(['user_id' => $user->id, 'entity_type' => User::class, 'action' => 'auth.login', 'created_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Kurikulum diajukan')->assertDontSee('Private snapshot')->assertDontSee('192.0.2.1');
        $activities = $response->viewData('content')['activities'];
        $this->assertCount(5, $activities);
        $this->assertSame([7, 6, 5, 4, 3], $activities->pluck('id')->all());
    }

    public function test_faculty_counts_and_overview_exclude_other_faculties_despite_forged_input(): void
    {
        $own = StudyProgram::factory()->create(['name' => 'Prodi Fakultas Sendiri']);
        $sibling = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id]);
        $other = StudyProgram::factory()->create(['name' => 'Prodi Rahasia Fakultas Lain']);
        $user = $this->userFor(UserRole::FACULTY_ADMIN, $own);
        Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Kurikulum Aktif Sendiri', 'status' => CurriculumStatus::PUBLISHED, 'created_at' => now()->subDays(2)]);
        Curriculum::factory()->create(['study_program_id' => $own->id, 'status' => CurriculumStatus::REVISION]);
        Curriculum::factory()->create(['study_program_id' => $sibling->id, 'status' => CurriculumStatus::SUBMITTED]);
        Curriculum::factory()->count(3)->create(['study_program_id' => $other->id, 'name' => 'Kurikulum Rahasia Fakultas Lain']);

        $this->actingAs($user)->get(route('dashboard', ['faculty_id' => $other->faculty_id]))
            ->assertOk()->assertSee($own->name)->assertSee('Kurikulum Aktif Sendiri')
            ->assertSee('Perlu Revisi')->assertDontSee($other->name)->assertDontSee('Kurikulum Rahasia Fakultas Lain')
            ->assertViewHas('metrics', [
                'Total Program Studi' => 2, 'Total Kurikulum' => 3,
                'Dipublikasikan' => 1, 'Menunggu Review' => 1, 'Perlu Revisi' => 1,
            ]);
    }

    public function test_prodi_dashboard_counts_courses_documents_and_versions_only_in_own_prodi(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id, 'name' => 'Prodi Lain']);
        $user = $this->userFor(UserRole::STUDY_PROGRAM_ADMIN, $own);
        $published = Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Kurikulum Aktif Prodi', 'status' => CurriculumStatus::PUBLISHED, 'created_at' => now()->subYear()]);
        $latest = Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Versi Revisi Terbaru', 'status' => CurriculumStatus::REVISION]);
        $foreign = Curriculum::factory()->create(['study_program_id' => $other->id, 'name' => 'Versi Prodi Lain', 'status' => CurriculumStatus::PUBLISHED]);
        Course::factory()->count(2)->create(['study_program_id' => $own->id]);
        Course::factory()->count(5)->create(['study_program_id' => $other->id]);
        Document::factory()->count(2)->create(['curriculum_id' => $published->id]);
        Document::factory()->create(['curriculum_id' => $latest->id]);
        Document::factory()->count(4)->create(['curriculum_id' => $foreign->id]);

        $response = $this->actingAs($user)->get(route('dashboard', ['study_program_id' => $other->id]))
            ->assertOk()->assertSee($own->name)->assertSee($published->name)->assertSee($latest->name)
            ->assertSee('Perlu Revisi')->assertDontSee($foreign->name)
            ->assertViewHas('metrics', ['Total Versi Kurikulum' => 2, 'Total Mata Kuliah' => 2, 'Total Dokumen Kurikulum' => 3]);
        $this->assertTrue($response->viewData('content')['latest']->is($latest));
        $this->assertTrue($response->viewData('content')['published']->is($published));
        $response->assertDontSee('href="'.route('admin.curricula.edit', $latest).'"', false);
    }

    public function test_head_queue_and_review_history_are_scoped_to_own_program(): void
    {
        $own = StudyProgram::factory()->create();
        $other = StudyProgram::factory()->create(['faculty_id' => $own->faculty_id]);
        $user = $this->userFor(UserRole::HEAD_OF_STUDY_PROGRAM, $own);
        $pending = Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Pengajuan Sendiri', 'status' => CurriculumStatus::SUBMITTED]);
        $approved = Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Review Sendiri', 'status' => CurriculumStatus::APPROVED]);
        $published = Curriculum::factory()->create(['study_program_id' => $own->id, 'name' => 'Aktif Sendiri', 'status' => CurriculumStatus::PUBLISHED]);
        $foreign = Curriculum::factory()->create(['study_program_id' => $other->id, 'name' => 'Pengajuan Rahasia', 'status' => CurriculumStatus::SUBMITTED]);
        foreach ([$approved, $foreign] as $curriculum) {
            CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $user->id, 'status' => 'approved', 'reviewed_at' => now()]);
        }

        $response = $this->actingAs($user)->get(route('dashboard', ['study_program_id' => $other->id]))
            ->assertOk()->assertSee($pending->name)->assertSee($approved->name)->assertSee($published->name)->assertDontSee($foreign->name)
            ->assertViewHas('metrics', ['Menunggu Review' => 1, 'Disetujui' => 1, 'Dipublikasikan' => 1]);
        $this->assertSame([$pending->id], $response->viewData('content')['queue']->pluck('id')->all());
        $this->assertSame([$approved->id], $response->viewData('content')['reviews']->pluck('curriculum_id')->all());
        $response->assertSee('href="'.route('kaprodi.submissions.show', $pending).'"', false);
    }

    #[DataProvider('roles')]
    public function test_missing_assignment_grants_global_data_only_to_university_admin(UserRole $role): void
    {
        $foreign = Curriculum::factory()->create(['name' => 'Data Asing', 'status' => CurriculumStatus::SUBMITTED]);
        if ($role === UserRole::UNIVERSITY_ADMIN) {
            $this->actingAs($this->userFor($role))->get(route('dashboard'))
                ->assertOk()->assertViewHas('missingScope', false)
                ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['Total Kurikulum'] === 1);

            return;
        }
        $this->actingAs($this->userFor($role))->get(route('dashboard', ['study_program_id' => $foreign->study_program_id]))
            ->assertOk()->assertSee('Penugasan Anda belum lengkap.')
            ->assertDontSee($foreign->name)
            ->assertViewHas('missingScope', true)
            ->assertViewHas('metrics', fn (array $metrics): bool => array_sum($metrics) === 0);
    }

    /** @return array<string, array{CurriculumStatus, string}> */
    public static function statuses(): array
    {
        return [
            'draft' => [CurriculumStatus::DRAFT, 'Draft'],
            'submitted' => [CurriculumStatus::SUBMITTED, 'Menunggu Review'],
            'revision' => [CurriculumStatus::REVISION, 'Perlu Revisi'],
            'approved' => [CurriculumStatus::APPROVED, 'Disetujui'],
            'published' => [CurriculumStatus::PUBLISHED, 'Dipublikasikan'],
            'archived' => [CurriculumStatus::ARCHIVED, 'Diarsipkan'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_latest_curriculum_status_has_indonesian_label(CurriculumStatus $status, string $label): void
    {
        $program = StudyProgram::factory()->create();
        $user = $this->userFor(UserRole::STUDY_PROGRAM_ADMIN, $program);
        Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => $status]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($label);
        $this->assertSame($label, $status->label());
    }

    public function test_faculty_overview_is_bounded_without_per_program_queries(): void
    {
        $program = StudyProgram::factory()->create();
        $user = $this->userFor(UserRole::FACULTY_ADMIN, $program);
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $firstCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $programs = StudyProgram::factory()->count(11)->create(['faculty_id' => $program->faculty_id]);
        foreach ($programs as $additional) {
            Curriculum::factory()->create(['study_program_id' => $additional->id]);
        }
        $user->unsetRelation('faculty');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('dashboard'))->assertOk();
        $secondCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(10, $response->viewData('content')['programs']);
        $this->assertSame(12, $response->viewData('metrics')['Total Program Studi']);
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_head_queue_and_history_are_bounded_but_counts_include_all_records(): void
    {
        $program = StudyProgram::factory()->create();
        $user = $this->userFor(UserRole::HEAD_OF_STUDY_PROGRAM, $program);
        $curricula = Curriculum::factory()->count(12)->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED, 'submitted_at' => now()]);
        foreach ($curricula as $curriculum) {
            CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $user->id, 'status' => 'revision', 'reviewed_at' => now()->subDay()]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertCount(10, $response->viewData('content')['queue']);
        $this->assertCount(5, $response->viewData('content')['reviews']);
        $this->assertSame(12, $response->viewData('metrics')['Menunggu Review']);
        $this->assertSame($curricula->take(10)->pluck('id')->all(), $response->viewData('content')['queue']->pluck('id')->all());
    }
}
