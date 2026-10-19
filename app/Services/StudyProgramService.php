<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudyProgramService
{
    /** @param array{faculty_id: int, name: string, code: string, degree: string, description: ?string, accreditation: ?string, website: ?string, is_active: bool} $data */
    public function save(User $actor, array $data, ?StudyProgram $studyProgram = null): StudyProgram
    {
        try {
            return DB::transaction(function () use ($actor, $data, $studyProgram): StudyProgram {
                $isNew = $studyProgram === null;
                $record = $isNew ? new StudyProgram : StudyProgram::query()->lockForUpdate()->findOrFail($studyProgram->id);
                $faculty = Faculty::query()->lockForUpdate()->find($data['faculty_id']);

                if (! $faculty || (! $faculty->is_active && ($isNew || $record->faculty_id !== $faculty->id))) {
                    throw ValidationException::withMessages(['faculty_id' => 'Pilih fakultas aktif atau pertahankan fakultas saat ini.']);
                }

                $oldValues = $isNew ? null : $record->getAttributes();
                $record->fill(Arr::only($data, ['faculty_id', 'name', 'code', 'degree', 'description', 'accreditation', 'website', 'is_active']));

                if ($isNew) {
                    $record->slug = (new UniqueSlugGenerator)->generate(StudyProgram::query(), $data['name'], 'program-studi');
                }

                $record->save();

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => $isNew ? 'study_program.created' : 'study_program.updated',
                    'entity_type' => StudyProgram::class,
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
            if (StudyProgram::query()->where('faculty_id', $data['faculty_id'])->where('code', $data['code'])
                ->when($studyProgram, fn (Builder $query): Builder => $query->whereKeyNot($studyProgram->id))->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode Program Studi sudah digunakan dalam fakultas ini.']);
            }

            throw ValidationException::withMessages(['name' => 'Data Program Studi dengan nama ini baru saja disimpan. Silakan simpan kembali.']);
        }
    }
}
