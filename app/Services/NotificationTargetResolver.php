<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class NotificationTargetResolver
{
    /**
     * @param  Collection<int, Notification>  $notifications
     * @return array<int, string>
     */
    public function resolve(User $user, Collection $notifications): array
    {
        $ids = $notifications->map(fn (Notification $notification) => data_get($notification->data, 'curriculum_id'))
            ->filter(fn ($id) => is_scalar($id) && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false);
        $curricula = Curriculum::query()->with('studyProgram')->whereIn('id', $ids)->get()->keyBy('id');
        $targets = [];
        $hasScope = match ($user->role) {
            UserRole::UNIVERSITY_ADMIN => true,
            UserRole::FACULTY_ADMIN => $user->faculty_id !== null && $user->faculty !== null,
            default => $user->study_program_id !== null && $user->studyProgram !== null,
        };
        if (! $hasScope) {
            return [];
        }

        foreach ($notifications as $notification) {
            $id = data_get($notification->data, 'curriculum_id');
            $curriculum = is_scalar($id) ? $curricula->get($id) : null;
            if ($notification->user_id !== $user->id || ! $curriculum || ! Gate::forUser($user)->allows('view', $curriculum)) {
                continue;
            }

            $route = match (true) {
                Gate::forUser($user)->allows('review', $curriculum) => 'kaprodi.submissions.show',
                Gate::forUser($user)->allows('publish', $curriculum) => 'kaprodi.submissions.publish.confirm',
                Gate::forUser($user)->allows('update', $curriculum) => 'admin.curricula.edit',
                default => 'internal.curricula.history',
            };
            $targets[$notification->id] = route($route, $curriculum);
        }

        return $targets;
    }
}
