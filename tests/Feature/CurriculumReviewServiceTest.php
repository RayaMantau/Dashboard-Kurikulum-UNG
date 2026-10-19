<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumReviewService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kaprodi_can_approve_submitted_curriculum_from_own_study_program(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $service = app(CurriculumReviewService::class);

        $service->approve(
            $kaprodi,
            $curriculum,
            'Kurikulum telah sesuai.'
        );

        $curriculum->refresh();

        $this->assertSame(
            CurriculumStatus::APPROVED,
            $curriculum->status
        );

        $this->assertNotNull($curriculum->approved_at);

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $curriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::APPROVED->value,
        ]);
    }

    public function test_kaprodi_can_request_revision_for_submitted_curriculum(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $service = app(CurriculumReviewService::class);

        $service->requestRevision(
            $kaprodi,
            $curriculum,
            'Perbaiki distribusi mata kuliah semester 3.'
        );

        $curriculum->refresh();

        $this->assertSame(
            CurriculumStatus::REVISION,
            $curriculum->status
        );

        $this->assertDatabaseHas('curriculum_reviews', [
            'curriculum_id' => $curriculum->id,
            'reviewer_id' => $kaprodi->id,
            'status' => CurriculumStatus::REVISION->value,
            'notes' => 'Perbaiki distribusi mata kuliah semester 3.',
        ]);
    }

    public function test_revision_requires_notes(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
        ]);

        $service = app(CurriculumReviewService::class);

        $this->expectException(DomainException::class);

        $service->requestRevision(
            $kaprodi,
            $curriculum,
            ''
        );
    }

    public function test_kaprodi_cannot_review_curriculum_from_another_study_program(): void
    {
        $kaprodiStudyProgram = StudyProgram::factory()->create();
        $otherStudyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $kaprodiStudyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $otherStudyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
        ]);

        $service = app(CurriculumReviewService::class);

        $this->expectException(DomainException::class);

        $service->approve(
            $kaprodi,
            $curriculum,
            'Disetujui.'
        );
    }

    public function test_study_program_admin_cannot_review_curriculum(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $admin = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
        ]);

        $service = app(CurriculumReviewService::class);

        $this->expectException(DomainException::class);

        $service->approve(
            $admin,
            $curriculum,
            'Disetujui.'
        );
    }

    public function test_only_submitted_curriculum_can_be_reviewed(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $service = app(CurriculumReviewService::class);

        $this->expectException(DomainException::class);

        $service->approve(
            $kaprodi,
            $curriculum,
            'Disetujui.'
        );
    }
}
