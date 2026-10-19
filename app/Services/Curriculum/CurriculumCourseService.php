<?php

namespace App\Services\Curriculum;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CurriculumCourseService
{
    private const AUDIT_FIELDS = ['curriculum_id', 'semester_id', 'course_id', 'course_code', 'course_name', 'sks', 'course_type', 'prerequisite_notes', 'is_active'];

    public function authorize(User $actor, Curriculum $curriculum, ?CurriculumCourse $assignment = null, bool $mutating = false): void
    {
        Gate::forUser($actor)->authorize('access-role', UserRole::STUDY_PROGRAM_ADMIN->value);
        abort_unless($actor->study_program_id !== null && $actor->studyProgram !== null, 403);
        Gate::forUser($actor)->authorize($mutating ? 'update' : 'view', $curriculum);
        abort_if($assignment !== null && $assignment->curriculum_id !== $curriculum->id, 403);
    }

    /** @param array{semester_id: int|string, course_id?: int|string, sks: int|float|string, course_type: string, prerequisite_notes?: ?string} $data */
    public function save(User $actor, Curriculum $curriculum, array $data, ?CurriculumCourse $assignment = null): CurriculumCourse
    {
        return DB::transaction(function () use ($actor, $curriculum, $data, $assignment): CurriculumCourse {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $record = $assignment ? CurriculumCourse::query()->findOrFail($assignment->id) : new CurriculumCourse;
            $this->authorize($actor, $curriculum, $assignment ? $record : null, true);

            $semesters = Semester::query()->where('curriculum_id', $curriculum->id)
                ->whereIn('id', array_filter([$record->semester_id, $data['semester_id']]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (! $semesters->has($data['semester_id'])) {
                throw ValidationException::withMessages(['semester_id' => 'Pilih semester milik kurikulum ini.']);
            }
            if ($record->exists) {
                abort_unless($semesters->has($record->semester_id), 403);
                $record = CurriculumCourse::query()->lockForUpdate()->findOrFail($record->id);
                $this->authorize($actor, $curriculum, $record, true);
            }
            $old = $record->exists ? Arr::only($record->getAttributes(), self::AUDIT_FIELDS) : null;
            if (! $record->exists) {
                $course = Course::query()->where('study_program_id', $curriculum->study_program_id)
                    ->whereKey($data['course_id'])->lockForUpdate()->first();
                if (! $course) {
                    throw ValidationException::withMessages(['course_id' => 'Pilih mata kuliah milik Program Studi ini.']);
                }
                if ($curriculum->curriculumCourses()->where('course_id', $course->id)->exists()) {
                    throw ValidationException::withMessages(['course_id' => 'Mata kuliah sudah ditambahkan pada kurikulum ini.']);
                }
                $record->curriculum_id = $curriculum->id;
                $record->course_id = $course->id;
                $record->is_active = true;
            }
            $record->fill(Arr::only($data, ['semester_id', 'sks', 'course_type', 'prerequisite_notes']));
            $record->save();
            $record->refresh();
            $this->audit($actor, $record, $assignment ? 'curriculum_course.updated' : 'curriculum_course.created', $old, Arr::only($record->getAttributes(), self::AUDIT_FIELDS));

            return $record;
        });
    }

    public function delete(User $actor, Curriculum $curriculum, CurriculumCourse $assignment): void
    {
        DB::transaction(function () use ($actor, $curriculum, $assignment): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $record = CurriculumCourse::query()->findOrFail($assignment->id);
            $this->authorize($actor, $curriculum, $record, true);
            $semester = Semester::query()->where('curriculum_id', $curriculum->id)->whereKey($record->semester_id)->lockForUpdate()->first();
            abort_unless($semester, 403);
            $record = CurriculumCourse::query()->lockForUpdate()->findOrFail($record->id);
            $old = Arr::only($record->getAttributes(), self::AUDIT_FIELDS);
            $record->delete();
            $this->audit($actor, $record, 'curriculum_course.deleted', $old, null);
        });
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(User $actor, CurriculumCourse $record, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'action' => $action, 'entity_type' => CurriculumCourse::class,
            'entity_id' => $record->id, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);
    }
}
