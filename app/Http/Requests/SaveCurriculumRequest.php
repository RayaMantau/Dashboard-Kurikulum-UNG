<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->study_program_id !== null && $this->user()->studyProgram !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only(['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description', '_token', '_method']));
    }

    protected function failedValidation(Validator $validator): void
    {
        request()->replace($this->only(['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description']));
        parent::failedValidation($validator);
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:0,65535'],
            'effective_year' => ['nullable', 'integer', 'between:0,65535'],
            'total_sks' => ['required', 'numeric', 'between:0,999.99', 'decimal:0,2'],
            'total_semesters' => ['required', 'integer', 'between:1,255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kurikulum wajib diisi.',
            'name.string' => 'Nama kurikulum harus berupa teks.',
            'name.max' => 'Nama kurikulum maksimal 255 karakter.',
            'year.required' => 'Tahun kurikulum wajib diisi.',
            'year.integer' => 'Tahun kurikulum harus berupa bilangan bulat.',
            'year.between' => 'Tahun kurikulum harus antara 0 dan 65535.',
            'effective_year.integer' => 'Tahun berlaku harus berupa bilangan bulat.',
            'effective_year.between' => 'Tahun berlaku harus antara 0 dan 65535.',
            'total_sks.required' => 'Total SKS wajib diisi.',
            'total_sks.numeric' => 'Total SKS harus berupa angka.',
            'total_sks.between' => 'Total SKS harus antara 0 dan 999,99.',
            'total_sks.decimal' => 'Total SKS maksimal dua angka desimal.',
            'total_semesters.required' => 'Jumlah semester wajib diisi.',
            'total_semesters.integer' => 'Jumlah semester harus berupa bilangan bulat.',
            'total_semesters.between' => 'Jumlah semester harus antara 1 dan 255.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 10.000 karakter.',
        ];
    }
}
