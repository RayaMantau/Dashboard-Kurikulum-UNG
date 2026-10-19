<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumReview;
use App\Models\Notification;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CurriculumReviewService
{
    public function approve(
        User $reviewer,
        Curriculum $curriculum,
        ?string $notes = null
    ): Curriculum {
        return DB::transaction(function () use ($reviewer, $curriculum, $notes) {
            $reviewer = User::query()->lockForUpdate()->findOrFail($reviewer->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $this->authorizeReview($reviewer, $curriculum);
            $this->validateReviewable($curriculum);
            $oldValues = $curriculum->getAttributes();

            CurriculumReview::create([
                'curriculum_id' => $curriculum->id,
                'reviewer_id' => $reviewer->id,
                'status' => CurriculumStatus::APPROVED->value,
                'notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $curriculum->update([
                'status' => CurriculumStatus::APPROVED,
                'approved_at' => now(),
            ]);

            $this->createAuditLog(
                $reviewer,
                $curriculum,
                'curriculum.approved',
                $oldValues
            );

            $this->notifyStudyProgramAdmins(
                $curriculum,
                'curriculum_approved',
                'Kurikulum Disetujui',
                "Kurikulum {$curriculum->name} telah disetujui oleh Kaprodi."
            );

            return $curriculum->fresh();
        });
    }

    public function requestRevision(
        User $reviewer,
        Curriculum $curriculum,
        string $notes
    ): Curriculum {
        return DB::transaction(function () use ($reviewer, $curriculum, $notes) {
            $reviewer = User::query()->lockForUpdate()->findOrFail($reviewer->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $this->authorizeReview($reviewer, $curriculum);
            $this->validateReviewable($curriculum);
            if (blank($notes)) {
                throw new DomainException('Catatan revisi wajib diisi.');
            }
            $oldValues = $curriculum->getAttributes();

            CurriculumReview::create([
                'curriculum_id' => $curriculum->id,
                'reviewer_id' => $reviewer->id,
                'status' => CurriculumStatus::REVISION->value,
                'notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $curriculum->update([
                'status' => CurriculumStatus::REVISION,
                'approved_at' => null,
            ]);

            $this->createAuditLog(
                $reviewer,
                $curriculum,
                'curriculum.revision_requested',
                $oldValues
            );

            $this->notifyStudyProgramAdmins(
                $curriculum,
                'curriculum_revision_requested',
                'Revisi Kurikulum Diperlukan',
                "Kurikulum {$curriculum->name} memerlukan revisi: {$notes}"
            );

            return $curriculum->fresh();
        });
    }

    private function authorizeReview(
        User $user,
        Curriculum $curriculum
    ): void {
        if (! $user->is_active) {
            throw new DomainException(
                'Pengguna tidak aktif dan tidak dapat mereview kurikulum.'
            );
        }

        if ($user->role !== UserRole::HEAD_OF_STUDY_PROGRAM) {
            throw new DomainException(
                'Hanya Kaprodi yang dapat mereview kurikulum.'
            );
        }

        if ($user->study_program_id !== $curriculum->study_program_id) {
            throw new DomainException(
                'Anda tidak memiliki akses untuk mereview kurikulum ini.'
            );
        }
    }

    private function validateReviewable(Curriculum $curriculum): void
    {
        if ($curriculum->status !== CurriculumStatus::SUBMITTED) {
            throw new DomainException(
                'Hanya kurikulum berstatus submitted yang dapat direview.'
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

    private function notifyStudyProgramAdmins(
        Curriculum $curriculum,
        string $type,
        string $title,
        string $message
    ): void {
        $admins = User::query()
            ->where('role', UserRole::STUDY_PROGRAM_ADMIN)
            ->where('study_program_id', $curriculum->study_program_id)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => [
                    'curriculum_id' => $curriculum->id,
                ],
            ]);
        }
    }
}
