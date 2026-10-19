<?php

namespace App\Http\Requests;

use App\Enums\CourseType;
use App\Models\CurriculumCourse;
use App\Services\Curriculum\CurriculumCourseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCurriculumCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(CurriculumCourseService::class)->authorize($this->user(), $this->route('curriculum'), $this->route('curriculumCourse'), true);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $curriculum = $this->route('curriculum');
        $rules = [
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'id')->where('curriculum_id', $curriculum->id)],
            'sks' => ['required', 'numeric', 'between:0,99.99', 'decimal:0,2'],
            'course_type' => ['required', Rule::enum(CourseType::class)],
            'prerequisite_notes' => ['nullable', 'string', 'max:10000'],
        ];
        if (! $this->route('curriculumCourse') instanceof CurriculumCourse) {
            $rules['course_id'] = ['required', 'integer', Rule::exists('courses', 'id')->where('study_program_id', $curriculum->study_program_id)];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester wajib dipilih.',
            'semester_id.integer' => 'Semester tidak valid.',
            'semester_id.exists' => 'Pilih semester milik kurikulum ini.',
            'course_id.required' => 'Mata kuliah wajib dipilih.',
            'course_id.integer' => 'Mata kuliah tidak valid.',
            'course_id.exists' => 'Pilih mata kuliah milik Program Studi ini.',
            'sks.required' => 'SKS wajib diisi.',
            'sks.numeric' => 'SKS harus berupa angka.',
            'sks.between' => 'SKS harus antara 0 dan 99,99.',
            'sks.decimal' => 'SKS maksimal dua angka desimal.',
            'course_type.required' => 'Jenis mata kuliah wajib dipilih.',
            'course_type.enum' => 'Jenis mata kuliah tidak valid.',
            'prerequisite_notes.string' => 'Catatan prasyarat harus berupa teks.',
            'prerequisite_notes.max' => 'Catatan prasyarat maksimal 10.000 karakter.',
        ];
    }
}
