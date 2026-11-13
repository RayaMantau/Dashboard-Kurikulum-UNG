<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CourseService
{
    private const AUDIT_FIELDS = ['study_program_id', 'code', 'name', 'description'];

    public function delete(User $actor, Course $course): void
    {
        DB::transaction(function () use ($actor, $course): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('viewAny', Course::class);
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('delete', $course);
            if ($course->curriculumCourses()->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['course' => 'Mata kuliah tidak dapat dihapus karena masih digunakan pada kurikulum.']);
            }
            $oldValues = Arr::only($course->getAttributes(), self::AUDIT_FIELDS);
            $course->delete();
            AuditLog::create([
                'user_id' => $actor->id, 'action' => 'course.deleted', 'entity_type' => Course::class,
                'entity_id' => $course->id, 'old_values' => $oldValues, 'new_values' => null,
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
            ]);
        });
    }

    /** @param array{code: string, name: string, description: ?string} $data */
    public function save(User $actor, array $data, ?Course $course = null): Course
    {
        try {
            return DB::transaction(function () use ($actor, $data, $course): Course {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($actor)->authorize('viewAny', Course::class);
                $record = $course ? Course::query()->lockForUpdate()->findOrFail($course->id) : new Course;
                Gate::forUser($actor)->authorize($course ? 'update' : 'create', $course ? $record : Course::class);

                $oldValues = $record->exists ? Arr::only($record->getAttributes(), self::AUDIT_FIELDS) : null;
                $record->fill(Arr::only($data, ['code', 'name', 'description']));
                if (! $record->exists) {
                    $record->study_program_id = $actor->study_program_id;
                }
                $record->save();

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => $course ? 'course.updated' : 'course.created',
                    'entity_type' => Course::class,
                    'entity_id' => $record->id,
                    'old_values' => $oldValues,
                    'new_values' => Arr::only($record->getAttributes(), self::AUDIT_FIELDS),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                return $record;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (Course::query()->where('study_program_id', $actor->fresh()->study_program_id)->where('code', $data['code'])
                ->when($course, fn (Builder $query): Builder => $query->whereKeyNot($course->id))->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode mata kuliah sudah digunakan dalam Program Studi ini.']);
            }

            throw $exception;
        }
    }
}
