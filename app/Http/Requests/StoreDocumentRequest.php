<?php

namespace App\Http\Requests;

use App\Services\Document\DocumentService;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(DocumentService::class)->authorize($this->user(), $this->route('curriculum'), null, true);

        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul dokumen wajib diisi.',
            'title.string' => 'Judul dokumen harus berupa teks.',
            'title.max' => 'Judul dokumen maksimal 255 karakter.',
            'file.required' => 'File dokumen wajib dipilih.',
            'file.file' => 'File dokumen tidak valid.',
            'file.uploaded' => 'File gagal diunggah.',
            'file.mimes' => 'Dokumen harus berupa PDF yang valid.',
            'file.mimetypes' => 'Isi dokumen harus berupa PDF.',
            'file.extensions' => 'Ekstensi dokumen harus .pdf.',
            'file.max' => 'Ukuran dokumen maksimal 10 MB.',
        ];
    }
}
