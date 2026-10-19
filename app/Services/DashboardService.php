<?php

namespace App\Services;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumReview;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    /**
     * @return array{
     *     role: UserRole, scopeName: string, missingScope: bool,
     *     statusCounts: array<string, int>, metrics: array<string, int>,
     *     content: array<string, mixed>
     * }
     */
    public function forUser(User $user): array
    {
        Gate::forUser($user)->authorize('access-internal-dashboard');

        $curricula = Curriculum::query();
        $scopeName = 'Universitas Negeri Gorontalo';
        $missingScope = false;

        if ($user->role === UserRole::FACULTY_ADMIN) {
            $missingScope = $user->faculty === null;
            $scopeName = $user->faculty?->name ?? 'Fakultas belum ditetapkan';
            $curricula->whereHas('studyProgram', fn (Builder $query) => $query->where('faculty_id', $user->faculty_id));
        } elseif ($user->role !== UserRole::UNIVERSITY_ADMIN) {
            $missingScope = $user->studyProgram === null;
            $scopeName = $user->studyProgram?->name ?? 'Program studi belum ditetapkan';
            $curricula->where('study_program_id', $user->study_program_id);
        }

        if ($missingScope) {
            $curricula->whereRaw('1 = 0');
        }

        $counts = (clone $curricula)->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')->toBase()->pluck('total', 'status');
        $statusCounts = [];
        foreach (CurriculumStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $data = match ($user->role) {
            UserRole::UNIVERSITY_ADMIN => $this->university($statusCounts),
            UserRole::FACULTY_ADMIN => $this->faculty($user, $statusCounts, $missingScope),
            UserRole::STUDY_PROGRAM_ADMIN => $this->studyProgram($user, $curricula, $statusCounts, $missingScope),
            UserRole::HEAD_OF_STUDY_PROGRAM => $this->head($curricula, $statusCounts),
        };

        return [
            'role' => $user->role,
            'scopeName' => $scopeName,
            'missingScope' => $missingScope,
            'statusCounts' => $statusCounts,
            ...$data,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{metrics: array<string, int>, content: array<string, mixed>}
     */
    private function university(array $counts): array
    {
        $activityLabels = [
            'curriculum.submitted' => 'Kurikulum diajukan',
            'curriculum.approved' => 'Kurikulum disetujui',
            'curriculum.revision_requested' => 'Revisi kurikulum diminta',
            'curriculum.published' => 'Kurikulum dipublikasikan',
            'curriculum.archived' => 'Kurikulum diarsipkan',
        ];

        return [
            'metrics' => [
                'Total Fakultas' => Faculty::query()->count(),
                'Total Program Studi' => StudyProgram::query()->count(),
                'Total Kurikulum' => array_sum($counts),
                'Dipublikasikan' => $counts['published'],
                'Menunggu Review' => $counts['submitted'],
                'Pengguna Internal Aktif' => User::query()->where('is_active', true)
                    ->whereIn('role', array_column(UserRole::cases(), 'value'))->count(),
            ],
            'content' => [
                'activities' => AuditLog::query()->select(['id', 'user_id', 'action', 'created_at'])
                    ->where('entity_type', Curriculum::class)->whereIn('action', array_keys($activityLabels))
                    ->with('user:id,name')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
                'activityLabels' => $activityLabels,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{metrics: array<string, int>, content: array<string, mixed>}
     */
    private function faculty(User $user, array $counts, bool $missingScope): array
    {
        $programs = StudyProgram::query()->where('faculty_id', $user->faculty_id);
        if ($missingScope) {
            $programs->whereRaw('1 = 0');
        }

        return [
            'metrics' => [
                'Total Program Studi' => (clone $programs)->count(),
                'Total Kurikulum' => array_sum($counts),
                'Dipublikasikan' => $counts['published'],
                'Menunggu Review' => $counts['submitted'],
                'Perlu Revisi' => $counts['revision'],
            ],
            'content' => [
                'programs' => $programs->select(['study_programs.id', 'study_programs.name'])->addSelect([
                    'published_curriculum_name' => Curriculum::query()->select('name')
                        ->whereColumn('study_program_id', 'study_programs.id')->where('status', CurriculumStatus::PUBLISHED)
                        ->orderByDesc('published_at')->orderByDesc('id')->limit(1),
                    'latest_curriculum_status' => Curriculum::query()->select('status')
                        ->whereColumn('study_program_id', 'study_programs.id')
                        ->orderByDesc('created_at')->orderByDesc('id')->limit(1),
                ])->orderBy('name')->orderBy('id')->limit(10)->get(),
            ],
        ];
    }

    /**
     * @param  Builder<Curriculum>  $curricula
     * @param  array<string, int>  $counts
     * @return array{metrics: array<string, int>, content: array<string, mixed>}
     */
    private function studyProgram(User $user, Builder $curricula, array $counts, bool $missingScope): array
    {
        $latest = (clone $curricula)->orderByDesc('created_at')->orderByDesc('id')->first();
        $workflowMessage = match ($latest?->status) {
            CurriculumStatus::DRAFT => 'Draft dapat dilengkapi sebelum diajukan untuk review.',
            CurriculumStatus::REVISION => 'Kurikulum memerlukan perbaikan sesuai catatan review.',
            CurriculumStatus::SUBMITTED => 'Kurikulum sedang menunggu review Ketua Program Studi.',
            CurriculumStatus::APPROVED => 'Kurikulum disetujui dan siap dipublikasikan oleh Ketua Program Studi.',
            CurriculumStatus::PUBLISHED => 'Kurikulum aktif telah dipublikasikan.',
            CurriculumStatus::ARCHIVED => 'Versi ini tersimpan sebagai riwayat kurikulum.',
            null => 'Belum ada versi kurikulum.',
        };

        return [
            'metrics' => [
                'Total Versi Kurikulum' => array_sum($counts),
                'Total Mata Kuliah' => $missingScope ? 0 : Course::query()->where('study_program_id', $user->study_program_id)->count(),
                'Total Dokumen Kurikulum' => Document::query()
                    ->whereIn('curriculum_id', (clone $curricula)->select('id'))->count(),
            ],
            'content' => [
                'published' => $this->published($curricula),
                'latest' => $latest,
                'workflowMessage' => $workflowMessage,
            ],
        ];
    }

    /**
     * @param  Builder<Curriculum>  $curricula
     * @param  array<string, int>  $counts
     * @return array{metrics: array<string, int>, content: array<string, mixed>}
     */
    private function head(Builder $curricula, array $counts): array
    {
        return [
            'metrics' => [
                'Menunggu Review' => $counts['submitted'],
                'Disetujui' => $counts['approved'],
                'Dipublikasikan' => $counts['published'],
            ],
            'content' => [
                'published' => $this->published($curricula),
                'queue' => (clone $curricula)->where('status', CurriculumStatus::SUBMITTED)
                    ->orderBy('submitted_at')->orderBy('id')->limit(10)->get(),
                'reviews' => CurriculumReview::query()
                    ->whereIn('curriculum_id', (clone $curricula)->select('id'))
                    ->whereIn('status', [CurriculumStatus::APPROVED->value, CurriculumStatus::REVISION->value])
                    ->whereNotNull('reviewed_at')->with('curriculum:id,name')
                    ->orderByDesc('reviewed_at')->orderByDesc('id')->limit(5)->get(),
            ],
        ];
    }

    /** @param Builder<Curriculum> $curricula */
    private function published(Builder $curricula): ?Curriculum
    {
        return (clone $curricula)->where('status', CurriculumStatus::PUBLISHED)
            ->orderByDesc('published_at')->orderByDesc('id')->first();
    }
}
