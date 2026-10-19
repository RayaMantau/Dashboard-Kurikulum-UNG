<?php

namespace App\Http\Requests;

use App\Services\Curriculum\SemesterService;
use Illuminate\Foundation\Http\FormRequest;

class SaveSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(SemesterService::class)->authorize($this->user(), $this->route('curriculum'), $this->route('semester'), true);

        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'semester_number' => ['required', 'integer', 'min:1', 'max:'.$this->route('curriculum')->total_semesters],
            'total_sks' => ['required', 'numeric', 'between:0,999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'semester_number.required' => 'Nomor semester wajib diisi.',
            'semester_number.integer' => 'Nomor semester harus berupa bilangan bulat.',
            'semester_number.min' => 'Nomor semester minimal 1.',
            'semester_number.max' => 'Nomor semester melebihi jumlah semester yang direncanakan.',
            'total_sks.required' => 'Total SKS wajib diisi.',
            'total_sks.numeric' => 'Total SKS harus berupa angka.',
            'total_sks.between' => 'Total SKS harus antara 0 dan 999,99.',
            'total_sks.decimal' => 'Total SKS maksimal dua angka desimal.',
        ];
    }
}
