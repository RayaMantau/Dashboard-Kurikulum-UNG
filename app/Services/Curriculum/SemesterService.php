<?php

namespace App\Services\Curriculum;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SemesterService
{
    private const FIELDS = ['curriculum_id', 'semester_number', 'name', 'total_sks'];

    public function authorize(User $actor, Curriculum $curriculum, ?Semester $semester = null, bool $mutating = false): void
    {
        Gate::forUser($actor)->authorize('access-role', UserRole::STUDY_PROGRAM_ADMIN->value);
        abort_unless($actor->study_program_id !== null && $actor->studyProgram !== null, 403);
        Gate::forUser($actor)->authorize($mutating ? 'update' : 'view', $curriculum);
        abort_if($semester !== null && $semester->curriculum_id !== $curriculum->id, 403);
    }

    /** @param array{semester_number: int|string, total_sks: int|float|string} $data */
    public function save(User $actor, Curriculum $curriculum, array $data, ?Semester $semester = null): Semester
    {
        return DB::transaction(function () use ($actor, $curriculum, $data, $semester): Semester {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $record = $semester ? Semester::query()->lockForUpdate()->findOrFail($semester->id) : new Semester;
            $this->authorize($actor, $curriculum, $semester ? $record : null, true);
            $number = (int) $data['semester_number'];
            if ($number < 1 || $number > $curriculum->total_semesters) {
                throw ValidationException::withMessages(['semester_number' => 'Nomor semester harus antara 1 dan '.$curriculum->total_semesters.'.']);
            }
            if ($curriculum->semesters()->where('semester_number', $number)->when($record->exists, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                throw ValidationException::withMessages(['semester_number' => 'Nomor semester sudah digunakan pada kurikulum ini.']);
            }
            $old = $record->exists ? Arr::only($record->getAttributes(), self::FIELDS) : null;
            $record->curriculum_id = $curriculum->id;
            $record->semester_number = $number;
            $record->name = 'Semester '.$number;
            $record->total_sks = $data['total_sks'];
            $record->save();
            $this->audit($actor, $record, $semester ? 'semester.updated' : 'semester.created', $old, Arr::only($record->fresh()->getAttributes(), self::FIELDS));

            return $record;
        });
    }

    public function delete(User $actor, Curriculum $curriculum, Semester $semester): void
    {
        DB::transaction(function () use ($actor, $curriculum, $semester): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $semester = Semester::query()->lockForUpdate()->findOrFail($semester->id);
            $this->authorize($actor, $curriculum, $semester, true);
            if ($semester->curriculumCourses()->exists()) {
                throw ValidationException::withMessages(['semester' => 'Semester tidak dapat dihapus karena masih memiliki mata kuliah.']);
            }
            $old = Arr::only($semester->getAttributes(), self::FIELDS);
            $semester->delete();
            $this->audit($actor, $semester, 'semester.deleted', $old, null);
        });
    }

    /** @param array<string, mixed>|null $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(User $actor, Semester $semester, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'action' => $action, 'entity_type' => Semester::class,
            'entity_id' => $semester->id, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);
    }
}
