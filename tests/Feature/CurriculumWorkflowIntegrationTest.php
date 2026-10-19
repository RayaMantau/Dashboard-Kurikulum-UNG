<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumPublicationService;
use App\Services\Curriculum\CurriculumReviewService;
use App\Services\Curriculum\CurriculumSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_curriculum_workflow_from_draft_until_published(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $admin = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        /*
         * Kurikulum lama yang saat ini masih aktif.
         */
        $oldCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'name' => 'Kurikulum 2020',
            'year' => 2020,
            'status' => CurriculumStatus::PUBLISHED,
            'published_at' => now()->subYears(4),
        ]);

        /*
         * Kurikulum baru.
         */
        $newCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'name' => 'Kurikulum 2025',
            'year' => 2025,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $semester = Semester::factory()->create([
            'curriculum_id' => $newCurriculum->id,
            'semester_number' => 1,
        ]);

        for ($number = 2; $number <= $newCurriculum->total_semesters; $number++) {
            Semester::factory()->create(['curriculum_id' => $newCurriculum->id, 'semester_number' => $number]);
        }

        $course = Course::factory()->create([
            'study_program_id' => $studyProgram->id,
        ]);

        $attachment = CurriculumCourse::factory()->create([
            'curriculum_id' => $newCurriculum->id,
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'sks' => 3,
            'course_type' => CourseType::REQUIRED,
        ]);

        $oldAttachment = CurriculumCourse::factory()->for($course)->create([
            'curriculum_id' => $oldCurriculum->id,
            'semester_id' => Semester::factory()->create(['curriculum_id' => $oldCurriculum->id])->id,
        ]);
        $originalIdentity = [$course->code, $course->name];
        $course->update(['code' => 'IF999', 'name' => 'Master baru setelah attachment']);
        $assertSnapshots = function () use ($attachment, $oldAttachment, $originalIdentity): void {
            foreach ([$attachment, $oldAttachment] as $record) {
                $record->refresh();
                $this->assertSame($originalIdentity, [$record->course_code, $record->course_name]);
            }
        };
        Document::factory()->create([
            'curriculum_id' => $newCurriculum->id,
            'uploaded_by' => $admin->id,
            'title' => 'Dokumen Kurikulum Resmi',
            'file_name' => 'kurikulum-2025.pdf',
            'file_path' => 'curricula/kurikulum-2025.pdf',
            'file_type' => 'pdf',
        ]);

        /*
         * STEP 1
         * Admin Prodi mengirim kurikulum.
         */
        app(CurriculumSubmissionService::class)
            ->submit($admin, $newCurriculum);

        $newCurriculum->refresh();
        $assertSnapshots();

        $this->assertSame(
            CurriculumStatus::SUBMITTED,
            $newCurriculum->status
        );

        /*
         * STEP 2
         * Kaprodi meminta revisi.
         */
        app(CurriculumReviewService::class)
            ->requestRevision(
                $kaprodi,
                $newCurriculum,
                'Perbaiki distribusi mata kuliah semester 1.'
            );

        $newCurriculum->refresh();
        $assertSnapshots();

        $this->assertSame(
            CurriculumStatus::REVISION,
            $newCurriculum->status
        );

        /*
         * STEP 3
         * Admin Prodi melakukan perbaikan lalu submit ulang.
         *
         * Dalam integration test ini kita menganggap perubahan
         * konten sudah dilakukan sebelum submit ulang.
         */
        app(CurriculumSubmissionService::class)
            ->submit($admin, $newCurriculum);

        $newCurriculum->refresh();
        $assertSnapshots();

        $this->assertSame(
            CurriculumStatus::SUBMITTED,
            $newCurriculum->status
        );

        /*
         * STEP 4
         * Kaprodi menyetujui kurikulum.
         */
        app(CurriculumReviewService::class)
            ->approve(
                $kaprodi,
                $newCurriculum,
                'Kurikulum telah sesuai dan dapat dipublikasikan.'
            );

        $newCurriculum->refresh();
        $assertSnapshots();

        $this->assertSame(
            CurriculumStatus::APPROVED,
            $newCurriculum->status
        );

        $this->assertNotNull(
            $newCurriculum->approved_at
        );

        /*
         * STEP 5
         * Kurikulum dipublikasikan.
         */
        app(CurriculumPublicationService::class)
            ->publish($kaprodi, $newCurriculum);

        $newCurriculum->refresh();
        $assertSnapshots();
        $oldCurriculum->refresh();

        /*
         * Kurikulum baru harus menjadi published.
         */
        $this->assertSame(
            CurriculumStatus::PUBLISHED,
            $newCurriculum->status
        );

        $this->assertNotNull(
            $newCurriculum->published_at
        );

        /*
         * Kurikulum lama otomatis archived.
         */
        $this->assertSame(
            CurriculumStatus::ARCHIVED,
            $oldCurriculum->status
        );

        $this->assertNotNull(
            $oldCurriculum->archived_at
        );

        /*
         * Tidak boleh terdapat lebih dari satu
         * published curriculum dalam satu Prodi.
         */
        $publishedCount = Curriculum::query()
            ->where('study_program_id', $studyProgram->id)
            ->where('status', CurriculumStatus::PUBLISHED)
            ->count();

        $this->assertSame(
            1,
            $publishedCount
        );

        /*
         * Harus terdapat dua review:
         *
         * 1. revision
         * 2. approved
         */
        $this->assertDatabaseCount(
            'curriculum_reviews',
            2
        );

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $newCurriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::REVISION->value,
        ]);

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $newCurriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::APPROVED->value,
        ]);

        /*
         * Audit trail utama harus tersedia.
         */
        $expectedAuditActions = [
            'curriculum.submitted',
            'curriculum.revision_requested',
            'curriculum.approved',
            'curriculum.archived',
            'curriculum.published',
        ];

        foreach ($expectedAuditActions as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
            ]);
        }

        /*
         * Karena submission dilakukan dua kali,
         * harus terdapat dua audit log submitted.
         */
        $this->assertSame(
            2,
            AuditLog::query()
                ->where('entity_id', $newCurriculum->id)
                ->where('entity_type', Curriculum::class)
                ->where('action', 'curriculum.submitted')
                ->count()
        );
    }
}
