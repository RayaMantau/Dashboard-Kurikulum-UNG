<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const AUDIT_FIELDS = ['name', 'email', 'role', 'faculty_id', 'study_program_id', 'is_active'];

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?User $target = null): User
    {
        try {
            return DB::transaction(function () use ($actor, $data, $target): User {
                $record = $target ? User::query()->lockForUpdate()->findOrFail($target->id) : new User;
                $role = UserRole::from($data['role']);

                if ($record->id === $actor->id) {
                    if ($role !== UserRole::UNIVERSITY_ADMIN) {
                        throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah role akun sendiri dari Admin Universitas.']);
                    }
                    if (! $data['is_active']) {
                        throw ValidationException::withMessages(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
                    }
                }

                $scope = $this->normalizeScope($role, $data, $record);
                $oldValues = $record->exists ? Arr::only($record->getAttributes(), self::AUDIT_FIELDS) : null;
                $record->fill([...Arr::only($data, ['name', 'email', 'role', 'is_active']), ...$scope]);

                if (filled($data['password'] ?? null)) {
                    $record->password = Hash::make($data['password']);
                }

                $record->save();

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => $target ? 'user.updated' : 'user.created',
                    'entity_type' => User::class,
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
            if (User::query()->where('email', $data['email'])->when($target, fn (Builder $query): Builder => $query->whereKeyNot($target->id))->exists()) {
                throw ValidationException::withMessages(['email' => 'Alamat email sudah digunakan.']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     * @return array{faculty_id: ?int, study_program_id: ?int}
     */
    private function normalizeScope(UserRole $role, array $data, User $current): array
    {
        $field = $role->scopeField();
        if ($field === null) {
            return ['faculty_id' => null, 'study_program_id' => null];
        }

        $id = $data[$field] ?? null;
        $retaining = $current->exists && $current->role === $role && (int) $current->{$field} === (int) $id;

        if ($field === 'faculty_id') {
            $faculty = Faculty::query()->lockForUpdate()->find($id);
            if (! $faculty || (! $faculty->is_active && ! $retaining)) {
                throw ValidationException::withMessages(['faculty_id' => 'Pilih fakultas aktif atau pertahankan scope saat ini dengan role yang sama.']);
            }

            return ['faculty_id' => $faculty->id, 'study_program_id' => null];
        }

        $program = StudyProgram::query()->lockForUpdate()->find($id);
        $faculty = $program ? Faculty::query()->lockForUpdate()->find($program->faculty_id) : null;
        if (! $program || ! $faculty || ((! $program->is_active || ! $faculty->is_active) && ! $retaining)) {
            throw ValidationException::withMessages(['study_program_id' => 'Pilih Program Studi dan fakultas yang aktif atau pertahankan scope saat ini dengan role yang sama.']);
        }

        return ['faculty_id' => $program->faculty_id, 'study_program_id' => $program->id];
    }
}
