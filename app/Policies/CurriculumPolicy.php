<?php

namespace App\Policies;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\User;

class CurriculumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Curriculum $curriculum): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::UNIVERSITY_ADMIN) {
            return true;
        }

        if ($user->role === UserRole::FACULTY_ADMIN) {
            return $curriculum->studyProgram->faculty_id === $user->faculty_id;
        }

        return $curriculum->study_program_id === $user->study_program_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role === UserRole::STUDY_PROGRAM_ADMIN;
    }

    public function update(User $user, Curriculum $curriculum): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role === UserRole::STUDY_PROGRAM_ADMIN
            && $curriculum->study_program_id === $user->study_program_id
            && in_array($curriculum->status, [
                CurriculumStatus::DRAFT,
                CurriculumStatus::REVISION,
            ], true);
    }

    public function submit(User $user, Curriculum $curriculum): bool
    {
        return $this->update($user, $curriculum)
            && in_array($curriculum->status, [
                CurriculumStatus::DRAFT,
                CurriculumStatus::REVISION,
            ], true);
    }

    public function review(User $user, Curriculum $curriculum): bool
    {
        return $user->is_active
            && $user->role === UserRole::HEAD_OF_STUDY_PROGRAM
            && $curriculum->study_program_id === $user->study_program_id
            && $curriculum->status === CurriculumStatus::SUBMITTED;
    }

    public function publish(User $user, Curriculum $curriculum): bool
    {
        return $user->is_active
            && $user->role === UserRole::HEAD_OF_STUDY_PROGRAM
            && $curriculum->study_program_id === $user->study_program_id
            && $curriculum->status === CurriculumStatus::APPROVED;
    }

    public function delete(User $user, Curriculum $curriculum): bool
    {
        return $user->is_active
            && $user->role === UserRole::STUDY_PROGRAM_ADMIN
            && $curriculum->study_program_id === $user->study_program_id
            && $curriculum->status === CurriculumStatus::DRAFT;
    }
}
