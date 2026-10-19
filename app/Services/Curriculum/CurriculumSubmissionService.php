<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CurriculumSubmissionService
{
    public function submit(User $user, Curriculum $curriculum): Curriculum
    {
        return DB::transaction(function () use ($user, $curriculum) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $this->authorize($user, $curriculum);
            $this->validateStatus($curriculum);
            $this->validateCompleteness($curriculum);
            $oldValues = $curriculum->getAttributes();

            $curriculum->update([
                'status' => CurriculumStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'curriculum.submitted',
                'entity_type' => Curriculum::class,
                'entity_id' => $curriculum->id,
                'old_values' => $oldValues,
                'new_values' => $curriculum->fresh()->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $this->notifyHeadOfStudyProgram($curriculum);

            return $curriculum->fresh();
        });
    }

    private function authorize(User $user, Curriculum $curriculum): void
    {
        if (! $user->is_active) {
            throw new DomainException(
                'Pengguna tidak aktif dan tidak dapat mengirim kurikulum.'
            );
        }

        if ($user->role !== UserRole::STUDY_PROGRAM_ADMIN) {
            throw new DomainException(
                'Hanya Admin Prodi yang dapat mengirim kurikulum.'
            );
        }

        if ($user->study_program_id !== $curriculum->study_program_id) {
            throw new DomainException(
                'Anda tidak memiliki akses ke kurikulum ini.'
            );
        }
    }

    private function validateStatus(Curriculum $curriculum): void
    {
        if (! in_array($curriculum->status, [
            CurriculumStatus::DRAFT,
            CurriculumStatus::REVISION,
        ], true)) {
            throw new DomainException(
                'Kurikulum hanya dapat dikirim dari status draft atau revision.'
            );
        }
    }

    /**
     * Completeness only; callers must authorize the actor before presenting this result.
     *
     * @return array{is_ready: bool, checks: array<string, array{label: string, passed: bool, message: string, errors: list<string>}>, errors: list<string>}
     */
    public function readiness(Curriculum $curriculum): array
    {
        $metadataErrors = [];
        if (blank($curriculum->name)) {
            $metadataErrors[] = 'Nama kurikulum wajib diisi.';
        }
        if (! $curriculum->year) {
            $metadataErrors[] = 'Tahun kurikulum wajib diisi.';
        }
        if ((float) $curriculum->total_sks <= 0) {
            $metadataErrors[] = 'Total SKS kurikulum harus lebih dari 0.';
        }
        if ((int) $curriculum->total_semesters <= 0) {
            $metadataErrors[] = 'Jumlah semester harus lebih dari 0.';
        }

        $numbers = $curriculum->semesters()->orderBy('semester_number')->pluck('semester_number')->all();
        $expected = $curriculum->total_semesters > 0 ? range(1, $curriculum->total_semesters) : [];
        $semesterErrors = [];
        if ($numbers === []) {
            $semesterErrors[] = 'Minimal harus terdapat satu semester.';
        }
        if ($numbers !== $expected) {
            $semesterErrors[] = 'Struktur semester harus lengkap dari Semester 1 sampai '.$curriculum->total_semesters.'.';
        }
        $missing = array_values(array_diff($expected, $numbers));
        $hasCourses = $curriculum->curriculumCourses()->exists();
        $hasDocuments = $curriculum->documents()->exists();
        $checks = [
            'metadata' => [
                'label' => 'Metadata kurikulum',
                'passed' => $metadataErrors === [],
                'message' => 'Nama, tahun, total SKS, dan jumlah semester telah diisi.',
                'errors' => $metadataErrors,
            ],
            'semesters' => [
                'label' => 'Struktur semester',
                'passed' => $semesterErrors === [],
                'message' => $missing !== []
                    ? 'Semester '.implode(', ', $missing).' belum tersedia.'
                    : 'Struktur harus tepat dari Semester 1 sampai '.$curriculum->total_semesters.'.',
                'errors' => $semesterErrors,
            ],
            'courses' => [
                'label' => 'Mata kuliah',
                'passed' => $hasCourses,
                'message' => 'Minimal satu mata kuliah telah ditambahkan.',
                'errors' => $hasCourses ? [] : ['Minimal harus terdapat satu mata kuliah.'],
            ],
            'documents' => [
                'label' => 'Dokumen kurikulum',
                'passed' => $hasDocuments,
                'message' => 'Minimal satu dokumen tercatat pada kurikulum.',
                'errors' => $hasDocuments ? [] : ['Dokumen kurikulum resmi wajib diunggah.'],
            ],
        ];
        $errors = array_merge(...array_column($checks, 'errors'));

        return ['is_ready' => $errors === [], 'checks' => $checks, 'errors' => $errors];
    }

    private function validateCompleteness(Curriculum $curriculum): void
    {
        $readiness = $this->readiness($curriculum);
        if (! $readiness['is_ready']) {
            throw new DomainException(implode(' ', $readiness['errors']));
        }
    }

    private function notifyHeadOfStudyProgram(Curriculum $curriculum): void
    {
        $reviewers = User::query()
            ->where('role', UserRole::HEAD_OF_STUDY_PROGRAM)
            ->where('study_program_id', $curriculum->study_program_id)
            ->where('is_active', true)
            ->get();

        foreach ($reviewers as $reviewer) {
            Notification::create([
                'user_id' => $reviewer->id,
                'type' => 'curriculum_submitted',
                'title' => 'Kurikulum Menunggu Review',
                'message' => "Kurikulum {$curriculum->name} telah dikirim dan menunggu review.",
                'data' => [
                    'curriculum_id' => $curriculum->id,
                ],
            ]);
        }
    }
}
