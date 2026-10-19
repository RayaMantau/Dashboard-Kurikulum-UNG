<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_program_admin_can_view_own_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
        ]);

        $this->assertTrue(
            $user->can('view', $curriculum)
        );
    }

    public function test_study_program_admin_cannot_view_other_study_program_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $ownStudyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $otherStudyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $ownStudyProgram->id,
            'is_active' => true,
        ]);

        $otherCurriculum = Curriculum::factory()->create([
            'study_program_id' => $otherStudyProgram->id,
            'created_by' => $user->id,
        ]);

        $this->assertFalse(
            $user->can('view', $otherCurriculum)
        );
    }

    public function test_study_program_admin_can_update_own_draft_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
            'status' => CurriculumStatus::DRAFT,
        ]);

        $this->assertTrue(
            $user->can('update', $curriculum)
        );
    }

    public function test_study_program_admin_cannot_update_published_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'created_by' => $user->id,
            'status' => CurriculumStatus::PUBLISHED,
        ]);

        $this->assertFalse(
            $user->can('update', $curriculum)
        );
    }

    public function test_head_of_study_program_can_review_submitted_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
        ]);

        $this->assertTrue(
            $user->can('review', $curriculum)
        );
    }

    public function test_head_of_study_program_cannot_edit_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::HEAD_OF_STUDY_PROGRAM,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
            'status' => CurriculumStatus::SUBMITTED,
        ]);

        $this->assertFalse(
            $user->can('update', $curriculum)
        );
    }

    public function test_inactive_user_cannot_view_curriculum(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => false,
        ]);

        $curriculum = Curriculum::factory()->create([
            'study_program_id' => $studyProgram->id,
        ]);

        $this->assertFalse(
            $user->can('view', $curriculum)
        );
    }
}
