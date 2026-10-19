<div class="form-field">
    <label for="faculty_id">Fakultas <span>(wajib)</span></label>
    <select id="faculty_id" name="faculty_id" required aria-invalid="{{ $errors->has('faculty_id') ? 'true' : 'false' }}" @error('faculty_id') aria-describedby="faculty_id-error" @enderror>
        <option value="">Pilih Fakultas</option>
        @foreach($faculties as $faculty)
            <option value="{{ $faculty->id }}" @selected((string) old('faculty_id', $studyProgram->faculty_id) === (string) $faculty->id)>{{ $faculty->name }}{{ $faculty->is_active ? '' : ' (Tidak Aktif - fakultas saat ini)' }}</option>
        @endforeach
    </select>
    @if($faculties->isEmpty())<p class="form-help">Belum ada fakultas aktif. Aktifkan atau tambahkan fakultas sebelum membuat Program Studi.</p>@endif
    @error('faculty_id')<p id="faculty_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="name">Nama Program Studi <span>(wajib)</span></label>
    <input id="name" name="name" type="text" value="{{ old('name', $studyProgram->name) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
    @error('name')<p id="name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="code">Kode Program Studi <span>(wajib)</span></label>
    <input id="code" name="code" type="text" value="{{ old('code', $studyProgram->code) }}" maxlength="255" required
        aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" aria-describedby="code-help @error('code') code-error @enderror">
    <p id="code-help" class="form-help">Gunakan kode Program Studi yang unik dalam fakultas. Kode disimpan dalam huruf kapital.</p>
    @error('code')<p id="code-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="degree">Jenjang <span>(wajib)</span></label>
    <input id="degree" name="degree" type="text" maxlength="255" required value="{{ old('degree', $studyProgram->degree) }}" placeholder="Contoh: S1"
        aria-invalid="{{ $errors->has('degree') ? 'true' : 'false' }}" @error('degree') aria-describedby="degree-error" @enderror>
    @error('degree')<p id="degree-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="accreditation">Akreditasi <span>(opsional)</span></label>
    <input id="accreditation" name="accreditation" type="text" maxlength="255" value="{{ old('accreditation', $studyProgram->accreditation) }}"
        aria-invalid="{{ $errors->has('accreditation') ? 'true' : 'false' }}" @error('accreditation') aria-describedby="accreditation-error" @enderror>
    @error('accreditation')<p id="accreditation-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="website">Website <span>(opsional)</span></label>
    <input id="website" name="website" type="url" maxlength="255" value="{{ old('website', $studyProgram->website) }}" placeholder="https://..."
        aria-invalid="{{ $errors->has('website') ? 'true' : 'false' }}" @error('website') aria-describedby="website-error" @enderror>
    @error('website')<p id="website-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="description">Deskripsi <span>(opsional)</span></label>
    <textarea id="description" name="description" rows="5" maxlength="10000"
        aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $studyProgram->description) }}</textarea>
    @error('description')<p id="description-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox-label" for="is_active">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $studyProgram->is_active))
            aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" aria-describedby="status-help @error('is_active') is_active-error @enderror">
        Status Aktif
    </label>
    <p id="status-help" class="form-help">Hapus centang untuk menonaktifkan Program Studi. Kurikulum, mata kuliah, pengguna, dan dokumen terkait tetap tersimpan dan tidak diubah.</p>
    @error('is_active')<p id="is_active-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
