<div class="form-field">
    <label for="name">Nama Kurikulum <span>(wajib)</span></label>
    <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $curriculum->name) }}"
        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
    @error('name')<p id="name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
@foreach([
    'year' => ['Tahun Kurikulum', 0, 65535, 1, true],
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
    <label for="description">Deskripsi <span>(opsional)</span></label>
    <textarea id="description" name="description" rows="6" maxlength="10000"
        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $curriculum->description) }}</textarea>
    @error('description')<p id="description-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
