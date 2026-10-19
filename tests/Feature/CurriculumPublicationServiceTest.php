<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumPublicationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumPublicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kaprodi_can_publish_approved_curriculum_from_own_study_program(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $service = app(CurriculumPublicationService::class);

        $service->publish($kaprodi, $curriculum);

        $curriculum->refresh();

        $this->assertSame(
            CurriculumStatus::PUBLISHED,
            $curriculum->status
        );

        $this->assertNotNull($curriculum->published_at);
    }

    public function test_publishing_new_curriculum_archives_previous_published_curriculum(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $oldCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::PUBLISHED,
            'published_at' => now()->subYear(),
        ]);

        $newCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $service = app(CurriculumPublicationService::class);

        $service->publish($kaprodi, $newCurriculum);

        $oldCurriculum->refresh();
        $newCurriculum->refresh();

        $this->assertSame(
            CurriculumStatus::ARCHIVED,
            $oldCurriculum->status
        );

        $this->assertNotNull($oldCurriculum->archived_at);

        $this->assertSame(
            CurriculumStatus::PUBLISHED,
            $newCurriculum->status
        );
    }

    public function test_only_one_curriculum_can_be_published_per_study_program(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $kaprodi = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::PUBLISHED,
        ]);

        $newCurriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::APPROVED,
        ]);

        $service = app(CurriculumPublicationService::class);

        $service->publish($kaprodi, $newCurriculum);

        $publishedCount = Curriculum::query()
            ->where('study_program_id', $studyProgram->id)
            ->where('status', CurriculumStatus::PUBLISHED)
            ->count();

        $this->assertSame(1, $publishedCount);
    }

    public function test_kaprodi_cannot_publish_curriculum_from_another_study_program(): void
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
            'status' => CurriculumStatus::APPROVED,
        ]);

        $service = app(CurriculumPublicationService::class);

        $this->expectException(DomainException::class);

        $service->publish($kaprodi, $curriculum);
    }

    public function test_study_program_admin_cannot_publish_curriculum(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $admin = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::APPROVED,
        ]);

        $service = app(CurriculumPublicationService::class);

        $this->expectException(DomainException::class);

        $service->publish($admin, $curriculum);
    }

    public function test_only_approved_curriculum_can_be_published(): void
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

        $service = app(CurriculumPublicationService::class);

        $this->expectException(DomainException::class);

        $service->publish($kaprodi, $curriculum);
    }
}
