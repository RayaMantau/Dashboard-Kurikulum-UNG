<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;

class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::UNIVERSITY_ADMIN;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $this->viewAny($user);
    }
}
