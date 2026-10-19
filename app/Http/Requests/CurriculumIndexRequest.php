<?php

namespace App\Http\Requests;

use App\Enums\CurriculumStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurriculumIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->study_program_id !== null && $this->user()->studyProgram !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(CurriculumStatus::class)],
            'year' => ['nullable', 'integer', 'between:0,65535'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'status.enum' => 'Filter status tidak valid.',
            'year.integer' => 'Filter tahun harus berupa bilangan bulat.',
            'year.between' => 'Filter tahun tidak valid.',
            'page.integer' => 'Nomor halaman tidak valid.',
            'page.min' => 'Nomor halaman minimal 1.',
        ];
    }
}
