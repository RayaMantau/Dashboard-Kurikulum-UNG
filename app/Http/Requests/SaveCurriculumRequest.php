<?php

namespace App\Http\Requests;

use App\Models\Document;
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
        $this->files->replace(array_intersect_key($this->files->all(), ['sk' => true]));
        $this->replace($this->only(['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description', 'ratified_at', 'external_document_url', '_token', '_method']));
    }

    protected function failedValidation(Validator $validator): void
    {
        request()->replace($this->only(['name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'description', 'ratified_at', 'external_document_url']));
        parent::failedValidation($validator);
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'sk' => [$this->route('curriculum')?->documents()->where('document_type', Document::RATIFICATION_DECREE)->exists() ? 'prohibited' : 'required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:10240'],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:0,65535'],
            'effective_year' => ['nullable', 'integer', 'between:0,65535'],
            'total_sks' => ['required', 'numeric', 'between:0,999.99', 'decimal:0,2'],
            'total_semesters' => ['required', 'integer', 'between:1,255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'ratified_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9995-12-31'],
            'external_document_url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ratified_at.required' => 'Tanggal pengesahan wajib diisi.',
            'external_document_url.required' => 'Link file kurikulum wajib diisi.',
            'sk.prohibited' => 'SK sudah tersedia. Hapus melalui Kelola Dokumen terlebih dahulu jika perlu menggantinya.',
            'sk.required' => 'SK pengesahan kurikulum wajib diunggah.',
            'sk.file' => 'File SK tidak valid.',
            'sk.uploaded' => 'File SK gagal diunggah.',
            'sk.mimes' => 'SK harus berupa PDF yang valid.',
            'sk.mimetypes' => 'Isi SK harus berupa PDF.',
            'sk.extensions' => 'Ekstensi SK harus .pdf.',
            'sk.max' => 'Ukuran SK maksimal 10 MB.',
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
            'ratified_at.date_format' => 'Tanggal pengesahan harus berupa tanggal yang valid (YYYY-MM-DD).',
            'ratified_at.after_or_equal' => 'Tanggal pengesahan paling awal 1 Januari 1000.',
            'ratified_at.before_or_equal' => 'Tanggal pengesahan paling akhir 31 Desember 9995 agar jadwal Monev dapat dihitung.',
            'external_document_url.string' => 'Link file kurikulum harus berupa teks.',
            'external_document_url.url' => 'Link file kurikulum harus berupa URL HTTP atau HTTPS yang valid.',
            'external_document_url.max' => 'Link file kurikulum maksimal 2.048 karakter.',
        ];
    }
}
