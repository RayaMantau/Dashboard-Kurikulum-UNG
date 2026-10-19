<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_program_admin_can_view_own_course(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $course = Course::factory()->create([
            'study_program_id' => $studyProgram->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $user->can('view', $course)
        );
    }

    public function test_study_program_admin_cannot_view_course_from_other_study_program(): void
    {
        $faculty = Faculty::factory()->create();

        $ownStudyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $otherStudyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $course = Course::factory()->create([
            'study_program_id' => $otherStudyProgram->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'faculty_id' => $faculty->id,
            'study_program_id' => $ownStudyProgram->id,
            'is_active' => true,
        ]);

        $this->assertFalse(
            $user->can('view', $course)
        );
    }

    public function test_faculty_admin_can_view_course_in_own_faculty(): void
    {
        $faculty = Faculty::factory()->create();

        $studyProgram = StudyProgram::factory()->create([
            'faculty_id' => $faculty->id,
        ]);

        $course = Course::factory()->create([
            'study_program_id' => $studyProgram->id,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::FACULTY_ADMIN,
            'faculty_id' => $faculty->id,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $user->can('view', $course)
        );
    }

    public function test_study_program_admin_can_create_course(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $user->can('create', Course::class)
        );
    }
}
