<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_curriculum_can_be_submitted_when_complete(): void
    {
        $user = User::factory()->create([
            'role' => 'study_program_admin',
            'is_active' => true,
        ]);

        $studyProgram = StudyProgram::factory()->create();

        $user->update([
            'study_program_id' => $studyProgram->id,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
            'status' => CurriculumStatus::DRAFT,
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
            'title' => 'Dokumen Kurikulum Resmi',
            'file_name' => 'kurikulum.pdf',
            'file_path' => 'curricula/kurikulum.pdf',
            'file_type' => 'pdf',
            'uploaded_by' => $user->id,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $service->submit($user, $curriculum);

        $curriculum->refresh();

        $this->assertSame(
            CurriculumStatus::SUBMITTED,
            $curriculum->status
        );

        $this->assertNotNull($curriculum->submitted_at);
    }

    public function test_incomplete_curriculum_cannot_be_submitted(): void
    {
        $user = User::factory()->create([
            'role' => 'study_program_admin',
            'is_active' => true,
        ]);

        $studyProgram = StudyProgram::factory()->create();

        $user->update([
            'study_program_id' => $studyProgram->id,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $this->expectException(\DomainException::class);

        $service->submit($user, $curriculum);
    }

    public function test_revision_curriculum_can_be_submitted_again(): void
    {
        $user = User::factory()->create([
            'role' => 'study_program_admin',
            'is_active' => true,
        ]);

        $studyProgram = StudyProgram::factory()->create();

        $user->update([
            'study_program_id' => $studyProgram->id,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
            'status' => CurriculumStatus::REVISION,
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
        ]);

        Document::factory()->create([
            'curriculum_id' => $curriculum->id,
            'title' => 'Dokumen Kurikulum Resmi',
            'file_name' => 'kurikulum.pdf',
            'file_path' => 'curricula/kurikulum.pdf',
            'file_type' => 'pdf',
            'uploaded_by' => $user->id,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $service->submit($user, $curriculum);

        $curriculum->refresh();

        $this->assertSame(
            CurriculumStatus::SUBMITTED,
            $curriculum->status
        );

        $this->assertNotNull($curriculum->submitted_at);
    }

    public function test_admin_cannot_submit_curriculum_from_another_study_program(): void
    {
        $adminStudyProgram = StudyProgram::factory()->create();
        $otherStudyProgram = StudyProgram::factory()->create();

        $user = User::factory()->create([
            'role' => 'study_program_admin',
            'study_program_id' => $adminStudyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $otherStudyProgram->id,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $this->expectException(\DomainException::class);

        $service->submit($user, $curriculum);
    }

    public function test_head_of_study_program_cannot_submit_curriculum(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $user = User::factory()->create([
            'role' => 'head_of_study_program',
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $this->expectException(\DomainException::class);

        $service->submit($user, $curriculum);
    }

    public function test_published_curriculum_cannot_be_submitted_again(): void
    {
        $studyProgram = StudyProgram::factory()->create();

        $user = User::factory()->create([
            'role' => 'study_program_admin',
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::PUBLISHED,
        ]);

        $service = app(CurriculumSubmissionService::class);

        $this->expectException(\DomainException::class);

        $service->submit($user, $curriculum);
    }
}
