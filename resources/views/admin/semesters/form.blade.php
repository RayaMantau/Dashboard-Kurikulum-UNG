@foreach(['semester_number' => ['Nomor Semester', 1, $curriculum->total_semesters, 1], 'total_sks' => ['Total SKS', 0, 999.99, 0.01]] as $field => [$label, $min, $max, $step])
    <div class="form-field">
        <label for="{{ $field }}">{{ $label }} <span>(wajib)</span></label>
        <input id="{{ $field }}" name="{{ $field }}" type="number" required min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
            value="{{ old($field, $semester->{$field}) }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="{{ $field }}-error" @enderror>
        @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endforeach
<p class="form-help">Nama semester mengikuti nomor yang dipilih. Total SKS diisi sebagai metadata semester.</p>
