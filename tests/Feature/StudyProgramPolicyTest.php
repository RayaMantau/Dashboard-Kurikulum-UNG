<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyProgramPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_admin_can_view_study_program_in_own_faculty(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::FACULTY_ADMIN,
            'faculty_id' => $faculty->id,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $user->can('view', $studyProgram)
        );
    }

    public function test_faculty_admin_cannot_view_study_program_in_other_faculty(): void
    {
        $ownFaculty = Faculty::factory()->create();
        $otherFaculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $otherFaculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::FACULTY_ADMIN,
            'faculty_id' => $ownFaculty->id,
            'is_active' => true,
        ]);

        $this->assertFalse(
            $user->can('view', $studyProgram)
        );
    }

    public function test_university_admin_can_view_any_study_program(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::UNIVERSITY_ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $user->can('view', $studyProgram)
        );
    }

    public function test_study_program_admin_can_view_only_own_study_program(): void
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

        $this->assertTrue(
            $user->can('view', $ownStudyProgram)
        );

        $this->assertFalse(
            $user->can('view', $otherStudyProgram)
        );
    }
}
