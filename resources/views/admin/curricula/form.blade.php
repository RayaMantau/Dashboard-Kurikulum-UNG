<div class="form-field">
    <label for="name">Nama Kurikulum <span>(wajib)</span></label>
    <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $curriculum->name) }}"
        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
    @error('name')<p id="name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
@foreach([
    'year' => ['Tahun Perbaikan Kurikulum', 0, 65535, 1, true],
    'effective_year' => ['Tahun Berlaku', 0, 65535, 1, false],
    'total_sks' => ['Total SKS', 0, 999.99, 0.01, true],
    'total_semesters' => ['Jumlah Semester', 1, 255, 1, true],
] as $field => [$label, $min, $max, $step, $required])
    <div class="form-field">
        <label for="{{ $field }}">{{ $label }} <span>({{ $required ? 'wajib' : 'opsional' }})</span></label>
        <input id="{{ $field }}" name="{{ $field }}" type="number" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" @required($required)
            value="{{ old($field, $curriculum->{$field}) }}"
            aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="{{ $field }}-error" @enderror>
        @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endforeach
<div class="form-field">
    <label for="ratified_at">Tanggal Pengesahan <span>(opsional)</span></label>
    <input id="ratified_at" name="ratified_at" type="date" min="1000-01-01" max="9995-12-31"
        value="{{ is_string(old('ratified_at', $curriculum->ratified_at?->format('Y-m-d'))) ? old('ratified_at', $curriculum->ratified_at?->format('Y-m-d')) : '' }}"
        aria-invalid="{{ $errors->has('ratified_at') ? 'true' : 'false' }}" aria-describedby="ratified-help @error('ratified_at') ratified-error @enderror">
    <p id="ratified-help">Tanggal pengesahan resmi, terpisah dari persetujuan review. Monev Minor dijadwalkan 2 tahun dan Rekonstruksi 4 tahun setelah tanggal ini.</p>
    @error('ratified_at')<p id="ratified-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="external_document_url">Link File Kurikulum <span>(opsional)</span></label>
    <input id="external_document_url" name="external_document_url" type="url" maxlength="2048"
        value="{{ is_string(old('external_document_url', $curriculum->external_document_url)) ? old('external_document_url', $curriculum->external_document_url) : '' }}"
        aria-invalid="{{ $errors->has('external_document_url') ? 'true' : 'false' }}" aria-describedby="external-link-help @error('external_document_url') external-link-error @enderror">
    <p id="external-link-help">Gunakan alamat HTTP/HTTPS yang dapat diakses publik. Link eksternal tidak menggantikan unggahan dokumen PDF untuk pengajuan review.</p>
    @error('external_document_url')<p id="external-link-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="description">Deskripsi <span>(opsional)</span></label>
    <textarea id="description" name="description" rows="6" maxlength="10000"
        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $curriculum->description) }}</textarea>
    @error('description')<p id="description-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
