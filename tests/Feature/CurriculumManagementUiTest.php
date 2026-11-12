<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumManagementUiTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'ratified_at' => '2026-07-15']);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);

        return [$user, $curriculum, $semester];
    }

    public function test_curriculum_table_has_dedicated_navigation_columns(): void
    {
        [$user, $curriculum] = $this->context();
        Document::factory()->count(2)->create(['curriculum_id' => $curriculum->id]);
        $this->travelTo(now()->setDate(2028, 7, 15));
        $response = $this->actingAs($user)->get(route('admin.curricula.index'))->assertOk()
            ->assertSeeInOrder(['Tahun Perbaikan Kurikulum', 'Tahun Berlaku', 'Total SKS', 'Semester', 'Tanggal Pengesahan', 'Monev Kurikulum', 'Dokumen', 'Status', 'Terakhir Diperbarui', 'Aksi'])
            ->assertSee('15 Juli 2026')->assertSee('Monev Minor')->assertSee('2 dokumen')
            ->assertDontSee('Jumlah Semester')->assertDontSee('>Reset<', false)->assertDontSee('Kembali ke beranda')
            ->assertSee(route('admin.courses.index'))->assertDontSee(route('admin.curricula.courses.index', $curriculum));
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $row = '//table[contains(@class,"curriculum-table")]/tbody/tr';
        $this->assertStringContainsString('Kelola Semester', $xpath->query($row.'/td[3]')->item(0)->textContent);
        $this->assertStringContainsString('Kelola Dokumen', $xpath->query($row.'/td[6]')->item(0)->textContent);
        $actions = $xpath->query($row.'/td[9]')->item(0)->textContent;
        foreach (['Edit', 'Riwayat', 'Ajukan Review'] as $label) {
            $this->assertStringContainsString($label, $actions);
        }
        foreach (['Semester', 'Dokumen', 'Mata Kuliah'] as $label) {
            $this->assertStringNotContainsString($label, $actions);
        }
    }

    public function test_semester_navigation_filters_assignments_and_moves_delete_to_edit(): void
    {
        [$user, $curriculum, $first] = $this->context();
        $second = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 2]);
        foreach ([$first, $second] as $i => $semester) {
            $course = Course::factory()->create(['study_program_id' => $user->study_program_id, 'code' => 'UI-COURSE-'.$i]);
            CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        }
        $filtered = route('admin.curricula.courses.index', [$curriculum, 'semester' => $first->id]);
        $this->actingAs($user)->get(route('admin.curricula.semesters.index', $curriculum))->assertOk()
            ->assertSee('Edit Semester')->assertSee('Kelola Mata Kuliah')->assertSee($filtered)
            ->assertDontSee('Hapus Semester')->assertDontSee('Berisi mata kuliah')->assertDontSee('value="DELETE"', false);
        $this->get($filtered)->assertOk()->assertSee('UI-COURSE-0')->assertDontSee('UI-COURSE-1');
        $this->get(route('admin.curricula.courses.index', $curriculum))->assertOk()->assertSee('UI-COURSE-0')->assertSee('UI-COURSE-1');
        $this->get(route('admin.curricula.semesters.edit', [$curriculum, $first]))->assertOk()->assertDontSee('Hapus Semester');
        $empty = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 3]);
        $this->get(route('admin.curricula.semesters.edit', [$curriculum, $empty]))->assertOk()
            ->assertSee('Zona Berbahaya')->assertSee('Konfirmasi penghapusan')->assertSee('Hapus Semester')
            ->assertSee('type="checkbox" required', false)->assertSee('value="DELETE"', false);
    }

    public function test_create_prefills_and_returns_to_persisted_semester(): void
    {
        [$user, $curriculum, $semester] = $this->context();
        $this->actingAs($user)->get(route('admin.curricula.courses.create', [$curriculum, 'semester' => $semester->id]))
            ->assertOk()->assertViewHas('assignment', fn ($assignment) => $assignment->semester_id === $semester->id);
        $course = Course::factory()->create(['study_program_id' => $user->study_program_id]);
        $this->post(route('admin.curricula.courses.store', [$curriculum, 'semester' => 999999]), [
            'semester_id' => $semester->id, 'course_id' => $course->id, 'sks' => 3, 'course_type' => 'wajib',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.curricula.courses.index', [$curriculum, 'semester' => $semester->id]));
        $this->assertDatabaseHas('curriculum_courses', ['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
    }

    public function test_foreign_and_malformed_semester_filters_fail_closed(): void
    {
        [$user, $curriculum] = $this->context();
        $foreign = Semester::factory()->create();
        $this->actingAs($user);
        foreach (['index', 'create'] as $action) {
            foreach ([$foreign->id, 999999, ['1'], 'invalid'] as $value) {
                $this->get(route('admin.curricula.courses.'.$action, [$curriculum, 'semester' => $value]))->assertForbidden();
            }
            $this->get(route('admin.curricula.courses.'.$action, [$foreign->curriculum_id, 'semester' => $foreign->id]))->assertForbidden();
        }
    }

    #[DataProvider('lockedStatuses')]
    public function test_locked_curricula_keep_read_only_navigation(CurriculumStatus $status): void
    {
        [$user, $curriculum, $semester] = $this->context();
        $curriculum->update(['status' => $status, 'ratified_at' => null]);
        $this->actingAs($user)->get(route('admin.curricula.index'))->assertOk()
            ->assertSee('Lihat Semester')->assertSee('Lihat Dokumen')->assertSee('Belum ditetapkan')->assertSee('Riwayat')
            ->assertDontSee('Kelola Semester')->assertDontSee('Kelola Dokumen')->assertDontSee('Ajukan Review');
        $this->get(route('admin.curricula.semesters.index', $curriculum))->assertOk()->assertSee('Lihat Mata Kuliah')
            ->assertDontSee('Edit Semester')->assertDontSee('Kelola Mata Kuliah')->assertDontSee('Hapus Semester');
        $this->get(route('admin.curricula.semesters.edit', [$curriculum, $semester]))->assertForbidden();
    }

    public function test_public_monitoring_has_no_management_controls(): void
    {
        $program = StudyProgram::factory()->create(['is_active' => true]);
        $program->faculty->update(['is_active' => true]);
        Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::PUBLISHED]);
        $response = $this->get(route('monitoring.index'))->assertOk()->assertSee($program->name);
        foreach (['Kelola Semester', 'Kelola Mata Kuliah', 'Kelola Dokumen', 'Ajukan Review', 'Hapus Semester'] as $label) {
            $response->assertDontSee($label);
        }
    }

    public static function lockedStatuses(): array
    {
        return array_map(fn ($status) => [$status], [CurriculumStatus::SUBMITTED, CurriculumStatus::APPROVED, CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED]);
    }
}
