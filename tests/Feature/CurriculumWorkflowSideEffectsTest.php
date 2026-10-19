<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
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

class CurriculumWorkflowSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_creates_audit_log_and_notifies_kaprodi(): void
    {
        [$studyProgram, $admin, $kaprodi] = $this->createActors();

        $curriculum = $this->createCompleteCurriculum(
            $studyProgram,
            $admin,
            CurriculumStatus::DRAFT
        );

        app(CurriculumSubmissionService::class)
            ->submit($admin, $curriculum);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'curriculum.submitted',
            'entity_type' => Curriculum::class,
            'entity_id' => $curriculum->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $kaprodi->id,
            'type' => 'curriculum_submitted',
        ]);
    }

    public function test_approval_creates_review_audit_log_and_notifies_admin(): void
    {
        [$studyProgram, $admin, $kaprodi] = $this->createActors();

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'status' => CurriculumStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        app(CurriculumReviewService::class)->approve(
            $kaprodi,
            $curriculum,
            'Kurikulum telah sesuai.'
        );

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $curriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::APPROVED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $kaprodi->id,
            'action' => 'curriculum.approved',
            'entity_type' => Curriculum::class,
            'entity_id' => $curriculum->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'curriculum_approved',
        ]);
    }

    public function test_revision_creates_review_with_notes_audit_log_and_notifies_admin(): void
    {
        [$studyProgram, $admin, $kaprodi] = $this->createActors();

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'status' => CurriculumStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $notes = 'Perbaiki distribusi mata kuliah semester 3.';

        app(CurriculumReviewService::class)->requestRevision(
            $kaprodi,
            $curriculum,
            $notes
        );

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $curriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::REVISION->value,
            'notes' => $notes,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $kaprodi->id,
            'action' => 'curriculum.revision_requested',
            'entity_type' => Curriculum::class,
            'entity_id' => $curriculum->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'curriculum_revision_requested',
        ]);
    }

    public function test_publication_creates_audit_logs_and_notifies_admin(): void
    {
        [$studyProgram, $admin, $kaprodi] = $this->createActors();

        $oldCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'status' => CurriculumStatus::PUBLISHED,
            'published_at' => now()->subYear(),
        ]);

        $newCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'status' => CurriculumStatus::APPROVED,
            'approved_at' => now(),
        ]);

        app(CurriculumPublicationService::class)
            ->publish($kaprodi, $newCurriculum);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $kaprodi->id,
            'action' => 'curriculum.archived',
            'entity_type' => Curriculum::class,
            'entity_id' => $oldCurriculum->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $kaprodi->id,
            'action' => 'curriculum.published',
            'entity_type' => Curriculum::class,
            'entity_id' => $newCurriculum->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'curriculum_published',
        ]);
    }

    private function createActors(): array
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

        return [$studyProgram, $admin, $kaprodi];
    }

    private function createCompleteCurriculum(
        StudyProgram $studyProgram,
        User $admin,
        CurriculumStatus $status
    ): Curriculum {
        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $admin->id,
            'status' => $status,
        ]);

        $semester = Semester::factory()->create([
            'curriculum_id' => $curriculum->id,
            'semester_number' => 1,
        ]);

        for ($number = 2; $number <= $curriculum->total_semesters; $number++) {
            Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => $number]);
        }

        $course = Course::factory()->create([
            'study_program_id' => $studyProgram->id,
        ]);

        CurriculumCourse::factory()->create([
            'curriculum_id' => $curriculum->id,
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'sks' => 3,
            'course_type' => CourseType::REQUIRED,
        ]);

        Document::factory()->create([
            'curriculum_id' => $curriculum->id,
            'uploaded_by' => $admin->id,
            'title' => 'Dokumen Kurikulum Resmi',
            'file_name' => 'kurikulum.pdf',
            'file_path' => 'curricula/kurikulum.pdf',
            'file_type' => 'pdf',
        ]);

        return $curriculum;
    }
}
