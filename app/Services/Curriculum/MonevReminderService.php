<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\StudyProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MonevReminderService
{
    public function __construct(private CurriculumMonitoringService $monitoring) {}

    public function send(): int
    {
        $sent = 0;
        StudyProgram::query()->where('is_active', true)
            ->whereHas('faculty', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('curricula', fn (Builder $query) => $query->where('status', CurriculumStatus::PUBLISHED))
            ->select('id')->chunkById(100, function ($programs) use (&$sent): void {
                foreach ($programs as $program) {
                    $sent += $this->sendForProgram($program->id);
                }
            });

        return $sent;
    }

    private function sendForProgram(int $programId): int
    {
        return DB::transaction(function () use ($programId): int {
            // Publication uses the same program mutex, including when switching versions.
            $program = StudyProgram::query()->lockForUpdate()->find($programId);
            if (! $program?->is_active || ! $program->faculty?->is_active) {
                return 0;
            }
            $curriculum = Curriculum::query()->where('study_program_id', $programId)
                ->where('status', CurriculumStatus::PUBLISHED)->orderByDesc('published_at')->orderByDesc('id')
                ->lockForUpdate()->first();
            if ($curriculum?->ratified_at === null) {
                return 0;
            }
            $schedule = $this->monitoring->schedule($curriculum);
            $today = CarbonImmutable::today(config('app.timezone'));
            $milestones = [
                'monev_minor_due' => [$schedule['minor'], 'Monev Minor Kurikulum', 'Monev Minor'],
                'reconstruction_due' => [$schedule['reconstruction'], 'Rekonstruksi Kurikulum', 'Rekonstruksi Kurikulum'],
            ];
            $recipients = User::query()->where('is_active', true)->where(function (Builder $query) use ($program): void {
                $query->where(fn (Builder $query) => $query->whereIn('role', [UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM])
                    ->where('study_program_id', $program->id))
                    ->orWhere(fn (Builder $query) => $query->where('role', UserRole::FACULTY_ADMIN)->where('faculty_id', $program->faculty_id));
            })->orderBy('id')->get();
            $sent = 0;
            foreach ($milestones as $type => [$due, $title, $period]) {
                if ($today->lessThan($due)) {
                    continue;
                }
                foreach ($recipients as $recipient) {
                    $exists = Notification::query()->where('user_id', $recipient->id)->where('type', $type)
                        ->where('data->curriculum_id', $curriculum->id)->exists();
                    if ($exists) {
                        continue;
                    }
                    Notification::create([
                        'user_id' => $recipient->id, 'type' => $type, 'title' => $title,
                        'message' => "Kurikulum Program Studi {$program->name} telah memasuki periode {$period}.",
                        'data' => ['curriculum_id' => $curriculum->id, 'study_program_id' => $program->id,
                            'reminder_type' => $type, 'due_date' => $due->toDateString()],
                    ]);
                    $sent++;
                }
            }

            return $sent;
        });
    }
}
