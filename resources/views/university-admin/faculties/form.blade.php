<div class="form-field">
    <label for="name">Nama Fakultas <span>(wajib)</span></label>
    <input id="name" name="name" type="text" value="{{ old('name', $faculty->name) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
    @error('name')<p id="name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="code">Kode Fakultas <span>(wajib)</span></label>
    <input id="code" name="code" type="text" value="{{ old('code', $faculty->code) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" aria-describedby="code-help @error('code') code-error @enderror">
    <p id="code-help" class="form-help">Gunakan kode unik fakultas. Kode disimpan dalam huruf kapital.</p>
    @error('code')<p id="code-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="description">Deskripsi <span>(opsional)</span></label>
    <textarea id="description" name="description" rows="5" maxlength="10000"
        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $faculty->description) }}</textarea>
    @error('description')<p id="description-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox-label" for="is_active">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $faculty->is_active))
            aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" aria-describedby="status-help @error('is_active') is_active-error @enderror">
        Status Aktif
    </label>
    <p id="status-help" class="form-help">Hapus centang untuk menonaktifkan fakultas. Program studi, pengguna, dan kurikulum terkait tetap tersimpan dan tidak diubah.</p>
    @error('is_active')<p id="is_active-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
