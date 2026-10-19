<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('is_active')) {
            $this->merge(['is_active' => false]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function userData(): array
    {
        return [...$this->safe()->except('password_confirmation'), 'is_active' => $this->boolean('is_active')];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $target = $this->route('user');
        $role = is_string($this->input('role')) ? UserRole::tryFrom($this->input('role')) : null;
        $scopeField = $role?->scopeField();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target instanceof User ? $target->id : null)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'faculty_id' => [Rule::excludeIf($scopeField !== 'faculty_id'), 'required', 'integer'],
            'study_program_id' => [Rule::excludeIf($scopeField !== 'study_program_id'), 'required', 'integer'],
            'is_active' => ['required', 'boolean'],
            'password' => [$target instanceof User ? 'nullable' : 'required', 'string', 'confirmed', Password::min(12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('Kata sandi maksimal 72 byte (72 karakter ASCII).');
                    }
                }],
            'password_confirmation' => ['required_with:password', 'nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'name.string' => 'Nama pengguna harus berupa teks.',
            'name.max' => 'Nama pengguna maksimal 255 karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.string' => 'Alamat email harus berupa teks.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Alamat email maksimal 255 karakter.',
            'email.unique' => 'Alamat email sudah digunakan.',
            'role.required' => 'Role wajib dipilih.',
            'role.enum' => 'Role pengguna tidak valid.',
            'faculty_id.required' => 'Fakultas wajib dipilih.',
            'faculty_id.integer' => 'Fakultas tidak valid.',
            'study_program_id.required' => 'Program Studi wajib dipilih.',
            'study_program_id.integer' => 'Program Studi tidak valid.',
            'is_active.required' => 'Pilih status pengguna.',
            'is_active.boolean' => 'Status pengguna tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.string' => 'Kata sandi harus berupa teks.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password_confirmation.required_with' => 'Konfirmasi kata sandi wajib diisi.',
            'password_confirmation.string' => 'Konfirmasi kata sandi harus berupa teks.',
        ];
    }
}
