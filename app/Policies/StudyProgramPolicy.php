<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StudyProgram;
use App\Models\User;

class StudyProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::UNIVERSITY_ADMIN;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, StudyProgram $studyProgram): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::UNIVERSITY_ADMIN) {
            return true;
        }

        if ($user->role === UserRole::FACULTY_ADMIN) {
            return $studyProgram->faculty_id === $user->faculty_id;
        }

        return $studyProgram->id === $user->study_program_id;
    }

    public function update(User $user, StudyProgram $studyProgram): bool
    {
        return $user->is_active
            && $user->role === UserRole::UNIVERSITY_ADMIN;
    }
}
