<div class="form-field">
    <label for="name">Nama Mata Kuliah <span>(wajib)</span></label>
    <input id="name" name="name" type="text" value="{{ old('name', $course->name) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
    @error('name')<p id="name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="code">Kode Mata Kuliah <span>(wajib)</span></label>
    <input id="code" name="code" type="text" value="{{ old('code', $course->code) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" aria-describedby="code-help @error('code') code-error @enderror">
    <p id="code-help" class="form-help">Gunakan kode mata kuliah yang unik dalam Program Studi ini. Kode disimpan dalam huruf kapital.</p>
    @error('code')<p id="code-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="description">Deskripsi <span>(opsional)</span></label>
    <textarea id="description" name="description" rows="5" maxlength="10000"
        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $course->description) }}</textarea>
    @error('description')<p id="description-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
