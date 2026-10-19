<?php

namespace App\Services\Curriculum;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CurriculumHistoryService
{
    private const EVENTS = [
        'curriculum.created' => 'Kurikulum dibuat',
        'curriculum.submitted' => 'Kurikulum diajukan untuk review',
        'curriculum.revision_requested' => 'Kaprodi meminta revisi',
        'curriculum.approved' => 'Kurikulum disetujui',
        'curriculum.published' => 'Kurikulum dipublikasikan',
        'curriculum.archived' => 'Kurikulum diarsipkan',
    ];

    /**
     * @return array{
     *     events: Collection<int, array{title: string, occurred_at: ?string, actor_name: string, actor_role: ?string}>,
     *     reviews: Collection<int, array{title: string, occurred_at: ?string, actor_name: string, actor_role: ?string, notes: ?string}>
     * }
     */
    public function history(User $actor, Curriculum $curriculum): array
    {
        Gate::forUser($actor)->authorize('view', $curriculum);
        abort_unless(match ($actor->role) {
            UserRole::UNIVERSITY_ADMIN => true,
            UserRole::FACULTY_ADMIN => $actor->faculty_id !== null && $actor->faculty !== null,
            UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM => $actor->study_program_id !== null && $actor->studyProgram !== null,
        }, 403);

        $events = AuditLog::query()
            ->where('entity_type', Curriculum::class)->where('entity_id', $curriculum->id)
            ->whereIn('action', array_keys(self::EVENTS))
            ->with('user:id,name,role')
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'user_id', 'action', 'created_at', 'old_values'])
            ->map(function (AuditLog $audit): array {
                $title = self::EVENTS[$audit->action];
                if ($audit->action === 'curriculum.submitted' && data_get($audit->old_values, 'status') === 'revision') {
                    $title = 'Kurikulum diajukan ulang untuk review';
                }

                return [
                    'title' => $title,
                    'occurred_at' => $audit->created_at?->copy()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'actor_name' => $audit->user?->name ?? 'Sistem',
                    'actor_role' => $audit->user?->role?->label(),
                ];
            });

        $reviews = $curriculum->reviews()->with('reviewer:id,name,role')
            ->orderBy('reviewed_at')->orderBy('id')
            ->get(['id', 'curriculum_id', 'reviewer_id', 'status', 'notes', 'reviewed_at'])
            ->map(fn (CurriculumReview $review): array => [
                'title' => match ($review->status) {
                    'revision' => 'Permintaan revisi',
                    'approved' => 'Persetujuan kurikulum',
                    default => 'Review kurikulum',
                },
                'occurred_at' => $review->reviewed_at?->copy()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'actor_name' => $review->reviewer?->name ?? 'Sistem',
                'actor_role' => $review->reviewer?->role?->label(),
                'notes' => $review->notes,
            ]);

        return compact('events', 'reviews');
    }
}
