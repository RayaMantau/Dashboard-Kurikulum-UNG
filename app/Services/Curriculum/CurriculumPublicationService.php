<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\StudyProgram;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CurriculumPublicationService
{
    public function publish(User $user, Curriculum $curriculum): Curriculum
    {
        return DB::transaction(function () use ($user, $curriculum) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->is_active || $user->role !== UserRole::HEAD_OF_STUDY_PROGRAM || $user->study_program_id === null) {
                throw new DomainException('Hanya Kaprodi aktif dengan penugasan Program Studi yang dapat mempublikasikan kurikulum.');
            }

            // A stable parent lock also serializes first publications when no published row exists.
            $program = StudyProgram::query()->lockForUpdate()->find($user->study_program_id);
            if ($program === null) {
                throw new DomainException('Penugasan Program Studi tidak tersedia.');
            }

            $relatedCurricula = Curriculum::query()
                ->where('study_program_id', $program->id)
                ->where(fn (Builder $query): Builder => $query->whereKey($curriculum->id)->orWhere('status', CurriculumStatus::PUBLISHED))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $curriculum = $relatedCurricula->firstWhere('id', $curriculum->id);
            if ($curriculum === null) {
                throw new DomainException('Anda tidak memiliki akses untuk mempublikasikan kurikulum ini.');
            }
            $this->authorize($user, $curriculum);
            $this->validateStatus($curriculum);

            $previousPublishedCurricula = $relatedCurricula->where('id', '!=', $curriculum->id);

            foreach ($previousPublishedCurricula as $previousCurriculum) {
                $oldValues = $previousCurriculum->getAttributes();

                $previousCurriculum->update([
                    'status' => CurriculumStatus::ARCHIVED,
                    'archived_at' => now(),
                ]);

                $this->createAuditLog(
                    $user,
                    $previousCurriculum,
                    'curriculum.archived',
                    $oldValues
                );
            }

            $oldValues = $curriculum->getAttributes();

            $curriculum->update([
                'status' => CurriculumStatus::PUBLISHED,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            $this->createAuditLog(
                $user,
                $curriculum,
                'curriculum.published',
                $oldValues
            );

            $this->notifyStudyProgramAdmins($curriculum);

            return $curriculum->fresh();
        });
    }

    private function authorize(User $user, Curriculum $curriculum): void
    {
        if (! $user->is_active) {
            throw new DomainException(
                'Pengguna tidak aktif dan tidak dapat mempublikasikan kurikulum.'
            );
        }

        if ($user->role !== UserRole::HEAD_OF_STUDY_PROGRAM) {
            throw new DomainException(
                'Hanya Kaprodi yang dapat mempublikasikan kurikulum.'
            );
        }

        if ($user->study_program_id !== $curriculum->study_program_id) {
            throw new DomainException(
                'Anda tidak memiliki akses untuk mempublikasikan kurikulum ini.'
            );
        }
    }

    private function validateStatus(Curriculum $curriculum): void
    {
        if ($curriculum->status !== CurriculumStatus::APPROVED) {
            throw new DomainException(
                'Hanya kurikulum berstatus approved yang dapat dipublikasikan.'
            );
        }
    }

    private function createAuditLog(
        User $user,
        Curriculum $curriculum,
        string $action,
        array $oldValues
    ): void {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => Curriculum::class,
            'entity_id' => $curriculum->id,
            'old_values' => $oldValues,
            'new_values' => $curriculum->fresh()->getAttributes(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function notifyStudyProgramAdmins(Curriculum $curriculum): void
    {
        $admins = User::query()
            ->where('role', UserRole::STUDY_PROGRAM_ADMIN)
            ->where('study_program_id', $curriculum->study_program_id)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'curriculum_published',
                'title' => 'Kurikulum Dipublikasikan',
                'message' => "Kurikulum {$curriculum->name} telah dipublikasikan.",
                'data' => [
                    'curriculum_id' => $curriculum->id,
                ],
            ]);
        }
    }
}
