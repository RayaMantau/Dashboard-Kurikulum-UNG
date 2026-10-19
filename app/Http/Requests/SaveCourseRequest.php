<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array{code: string, name: string, description: ?string} */
    public function courseData(): array
    {
        return [
            'code' => $this->validated('code'),
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('courses', 'code')
                ->where('study_program_id', $this->user()->study_program_id)
                ->ignore($course instanceof Course ? $course->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode mata kuliah wajib diisi.',
            'code.string' => 'Kode mata kuliah harus berupa teks.',
            'code.max' => 'Kode mata kuliah maksimal 255 karakter.',
            'code.unique' => 'Kode mata kuliah sudah digunakan dalam Program Studi ini.',
            'name.required' => 'Nama mata kuliah wajib diisi.',
            'name.string' => 'Nama mata kuliah harus berupa teks.',
            'name.max' => 'Nama mata kuliah maksimal 255 karakter.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 10.000 karakter.',
        ];
    }
}
