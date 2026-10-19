<?php

namespace App\Http\Requests;

use App\Models\StudyProgram;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudyProgramRequest extends FormRequest
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

    /** @return array{faculty_id: int, name: string, code: string, degree: string, description: ?string, accreditation: ?string, website: ?string, is_active: bool} */
    public function studyProgramData(): array
    {
        return [
            'faculty_id' => (int) $this->validated('faculty_id'),
            'name' => $this->validated('name'),
            'code' => $this->validated('code'),
            'degree' => $this->validated('degree'),
            'description' => $this->validated('description'),
            'accreditation' => $this->validated('accreditation'),
            'website' => $this->validated('website'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $studyProgram = $this->route('studyProgram');
        $current = $studyProgram instanceof StudyProgram ? $studyProgram : null;
        $facultyId = is_scalar($this->input('faculty_id')) ? $this->input('faculty_id') : null;

        return [
            'faculty_id' => ['bail', 'required', 'integer', Rule::exists('faculties', 'id')->where(
                fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('is_active', true)->orWhere('id', $current?->faculty_id))
            )],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('study_programs', 'code')
                ->where('faculty_id', $facultyId)->ignore($current?->id)],
            'degree' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'accreditation' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Fakultas wajib dipilih.',
            'faculty_id.integer' => 'Fakultas tidak valid.',
            'faculty_id.exists' => 'Pilih fakultas aktif atau pertahankan fakultas saat ini.',
            'name.required' => 'Nama Program Studi wajib diisi.',
            'name.string' => 'Nama Program Studi harus berupa teks.',
            'name.max' => 'Nama Program Studi maksimal 255 karakter.',
            'code.required' => 'Kode Program Studi wajib diisi.',
            'code.string' => 'Kode Program Studi harus berupa teks.',
            'code.max' => 'Kode Program Studi maksimal 255 karakter.',
            'code.unique' => 'Kode Program Studi sudah digunakan dalam fakultas ini.',
            'degree.required' => 'Jenjang wajib diisi.',
            'degree.string' => 'Jenjang harus berupa teks.',
            'degree.max' => 'Jenjang maksimal 255 karakter.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 10.000 karakter.',
            'accreditation.string' => 'Akreditasi harus berupa teks.',
            'accreditation.max' => 'Akreditasi maksimal 255 karakter.',
            'website.string' => 'Website harus berupa teks.',
            'website.max' => 'Website maksimal 255 karakter.',
            'website.url' => 'Website harus berupa URL dengan awalan http:// atau https://.',
            'is_active.required' => 'Pilih status Program Studi.',
            'is_active.boolean' => 'Status Program Studi tidak valid.',
        ];
    }
}
