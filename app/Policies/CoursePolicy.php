<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->create($user)
            && $user->study_program_id !== null
            && $user->studyProgram()->exists();
    }

    public function view(User $user, Course $course): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::UNIVERSITY_ADMIN) {
            return true;
        }

        if ($user->role === UserRole::FACULTY_ADMIN) {
            return $course->studyProgram->faculty_id === $user->faculty_id;
        }

        return $course->study_program_id === $user->study_program_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role === UserRole::STUDY_PROGRAM_ADMIN;
    }

    public function update(User $user, Course $course): bool
    {
        return $user->is_active
            && $user->role === UserRole::STUDY_PROGRAM_ADMIN
            && $course->study_program_id === $user->study_program_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->is_active
            && $user->role === UserRole::STUDY_PROGRAM_ADMIN
            && $course->study_program_id === $user->study_program_id;
    }
}
