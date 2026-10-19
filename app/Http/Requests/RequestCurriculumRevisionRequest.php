<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestCurriculumRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->study_program_id !== null
            && $this->user()->studyProgram !== null
            && $this->user()->can('review', $this->route('curriculum'));
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['notes' => ['required', 'string', 'max:10000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan revisi wajib diisi.',
            'notes.string' => 'Catatan revisi harus berupa teks.',
            'notes.max' => 'Catatan revisi maksimal 10.000 karakter.',
        ];
    }
}
