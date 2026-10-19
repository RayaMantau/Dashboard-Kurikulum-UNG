<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacultyService
{
    /** @param array{name: string, code: string, description?: ?string, is_active: bool|int|string} $data */
    public function save(User $actor, array $data, ?Faculty $faculty = null): Faculty
    {
        try {
            return DB::transaction(function () use ($actor, $data, $faculty): Faculty {
                $isNew = $faculty === null;
                $record = $isNew ? new Faculty : Faculty::query()->lockForUpdate()->findOrFail($faculty->id);
                $oldValues = $isNew ? null : $record->getAttributes();
                $record->fill(Arr::only($data, ['name', 'code', 'description', 'is_active']));

                if ($isNew) {
                    $record->slug = (new UniqueSlugGenerator)->generate(Faculty::query(), $data['name'], 'fakultas');
                }

                $record->save();

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => $isNew ? 'faculty.created' : 'faculty.updated',
                    'entity_type' => Faculty::class,
                    'entity_id' => $record->id,
                    'old_values' => $oldValues,
                    'new_values' => $record->getAttributes(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                return $record;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (Faculty::query()->where('code', $data['code'])->when($faculty, fn (Builder $query) => $query->whereKeyNot($faculty->id))->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode fakultas sudah digunakan.']);
            }

            throw ValidationException::withMessages(['name' => 'Data fakultas dengan nama ini baru saja disimpan. Silakan simpan kembali.']);
        }
    }
}
