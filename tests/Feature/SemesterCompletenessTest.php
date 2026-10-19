<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumSubmissionService;
use App\Services\Curriculum\SemesterService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SemesterCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public static function structures(): array
    {
        return [
            'complete' => [4, [1, 2, 3, 4], true],
            'gap' => [4, [1, 2, 4], false],
            'only one' => [4, [1], false],
            'wrong set same count' => [4, [1, 2, 4, 5], false],
            'single semester' => [1, [1], true],
        ];
    }

    private function context(int $total, array $numbers): array
    {
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $user->study_program_id, 'total_semesters' => $total]);
        foreach ($numbers as $number) {
            Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => $number]);
        }
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $curriculum->semesters()->where('semester_number', 1)->firstOrFail()->id]);
        Document::factory()->create(['curriculum_id' => $curriculum->id]);

        return [$user, $curriculum];
    }

    #[DataProvider('structures')]
    public function test_submission_requires_exact_semester_set(int $total, array $numbers, bool $valid): void
    {
        [$user, $curriculum] = $this->context($total, $numbers);
        try {
            app(CurriculumSubmissionService::class)->submit($user, $curriculum);
            $this->assertTrue($valid);
            $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
        } catch (DomainException $exception) {
            $this->assertFalse($valid);
            $this->assertStringContainsString('Struktur semester harus lengkap', $exception->getMessage());
            $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_deleted_empty_semester_invalidates_preloaded_completeness(): void
    {
        [$user, $curriculum] = $this->context(4, [1, 2, 3, 4]);
        $curriculum->load('semesters');
        $semester = $curriculum->semesters()->where('semester_number', 4)->firstOrFail();
        app(SemesterService::class)->delete($user, $curriculum, $semester);
        $this->assertDatabaseCount('semesters', 3);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Struktur semester harus lengkap');
        app(CurriculumSubmissionService::class)->submit($user, $curriculum);
    }

    public function test_complete_semesters_do_not_bypass_course_or_document_requirements(): void
    {
        [$user, $curriculum] = $this->context(1, [1]);
        $curriculum->curriculumCourses()->delete();
        $curriculum->documents()->delete();
        try {
            app(CurriculumSubmissionService::class)->submit($user, $curriculum);
            $this->fail('Course and document requirements must remain.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Minimal harus terdapat satu mata kuliah.', $exception->getMessage());
            $this->assertStringContainsString('Dokumen kurikulum resmi wajib diunggah.', $exception->getMessage());
        }
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
    }
}
