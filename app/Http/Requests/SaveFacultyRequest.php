<?php

namespace App\Http\Requests;

use App\Models\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFacultyRequest extends FormRequest
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

        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array{name: string, code: string, description: ?string, is_active: bool} */
    public function facultyData(): array
    {
        return [
            'name' => $this->validated('name'),
            'code' => $this->validated('code'),
            'description' => $this->validated('description'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $faculty = $this->route('faculty');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('faculties', 'code')->ignore($faculty instanceof Faculty ? $faculty->id : null)],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama fakultas wajib diisi.',
            'name.string' => 'Nama fakultas harus berupa teks.',
            'name.max' => 'Nama fakultas maksimal 255 karakter.',
            'code.required' => 'Kode fakultas wajib diisi.',
            'code.string' => 'Kode fakultas harus berupa teks.',
            'code.max' => 'Kode fakultas maksimal 255 karakter.',
            'code.unique' => 'Kode fakultas sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 10.000 karakter.',
            'is_active.required' => 'Pilih status fakultas.',
            'is_active.boolean' => 'Status fakultas tidak valid.',
        ];
    }
}
