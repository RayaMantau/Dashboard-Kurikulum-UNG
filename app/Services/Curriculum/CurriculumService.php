<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CurriculumService
{
    private const METADATA_FIELDS = ['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description'];

    private const AUDIT_FIELDS = ['study_program_id', 'name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description', 'status', 'created_by', 'updated_by'];

    /** @param array<string, mixed> $data */
    public function saveMetadata(User $actor, array $data, ?Curriculum $curriculum = null): Curriculum
    {
        return DB::transaction(function () use ($actor, $data, $curriculum): Curriculum {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($actor->study_program_id !== null && $actor->studyProgram !== null, 403);
            $record = $curriculum ? Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id) : new Curriculum;
            Gate::forUser($actor)->authorize($curriculum ? 'update' : 'create', $curriculum ? $record : Curriculum::class);
            if ($record->exists && isset($data['total_semesters']) && (int) $data['total_semesters'] < (int) $record->semesters()->max('semester_number')) {
                throw ValidationException::withMessages(['total_semesters' => 'Jumlah semester tidak boleh lebih kecil dari nomor semester tertinggi yang sudah ada.']);
            }
            $oldValues = $record->exists ? Arr::only($record->getAttributes(), self::AUDIT_FIELDS) : null;
            $record->fill(Arr::only($data, self::METADATA_FIELDS));
            if (! $record->exists) {
                $record->study_program_id = $actor->study_program_id;
                $record->status = CurriculumStatus::DRAFT;
                $record->created_by = $actor->id;
                $record->submitted_at = $record->approved_at = $record->published_at = $record->archived_at = null;
            }
            $record->updated_by = $actor->id;
            $record->save();
            $record->refresh();

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $curriculum ? 'curriculum.updated' : 'curriculum.created',
                'entity_type' => Curriculum::class,
                'entity_id' => $record->id,
                'old_values' => $oldValues,
                'new_values' => Arr::only($record->getAttributes(), self::AUDIT_FIELDS),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $record;
        });
    }

    /**
     * @param  array{search?: ?string, status?: ?string, year?: int|string|null, page?: int|string|null}  $filters
     * @return array{curricula: LengthAwarePaginator, years: Collection}
     */
    public function overview(User $user, array $filters): array
    {
        Gate::forUser($user)->authorize('access-role', UserRole::STUDY_PROGRAM_ADMIN->value);
        Gate::forUser($user)->authorize('viewAny', Curriculum::class);
        abort_unless($user->study_program_id !== null && $user->studyProgram !== null, 403);

        $query = Curriculum::query()->where('study_program_id', $user->study_program_id);
        $years = (clone $query)->select('year')->distinct()->orderByDesc('year')->pluck('year');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(fn (Builder $query): Builder => $query->whereLike('name', $search)->orWhereLike('year', $search));
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['year'] ?? null)) {
            $query->where('year', $filters['year']);
        }

        return [
            'curricula' => $query->orderByDesc('year')->orderByDesc('id')->paginate(15)->appends($filters),
            'years' => $years,
        ];
    }
}
