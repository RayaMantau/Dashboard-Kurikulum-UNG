@foreach(['name' => 'Nama', 'email' => 'Alamat email'] as $field => $label)
    <div class="form-field">
        <label for="{{ $field }}">{{ $label }} <span>(wajib)</span></label>
        <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="255" required value="{{ old($field, $managedUser->{$field}) }}"
            aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="{{ $field }}-error" @enderror>
        @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endforeach
<div class="form-field">
    <label for="role">Role <span>(wajib)</span></label>
    <select id="role" name="role" required data-user-role aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}" @error('role') aria-describedby="role-error" @enderror>
        <option value="">Pilih Role</option>
        @foreach($roles as $role)
            <option value="{{ $role->value }}" data-scope="{{ $role->scopeField() }}" @selected(old('role', $managedUser->role?->value) === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </select>
    @error('role')<p id="role-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field" data-user-scope="faculty_id">
    <label for="faculty_id">Fakultas <span>(wajib untuk Admin Fakultas)</span></label>
    <select id="faculty_id" name="faculty_id" aria-invalid="{{ $errors->has('faculty_id') ? 'true' : 'false' }}" @error('faculty_id') aria-describedby="faculty_id-error" @enderror>
        <option value="">Pilih Fakultas</option>
        @foreach($faculties as $faculty)
            <option value="{{ $faculty->id }}" @selected((string) old('faculty_id', $managedUser->faculty_id) === (string) $faculty->id)>{{ $faculty->name }}{{ $faculty->is_active ? '' : ' (Tidak Aktif - scope saat ini)' }}</option>
        @endforeach
    </select>
    @error('faculty_id')<p id="faculty_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field" data-user-scope="study_program_id">
    <label for="study_program_id">Program Studi <span>(wajib untuk Admin Prodi / Ketua Program Studi)</span></label>
    <select id="study_program_id" name="study_program_id" aria-invalid="{{ $errors->has('study_program_id') ? 'true' : 'false' }}" @error('study_program_id') aria-describedby="study_program_id-error" @enderror>
        <option value="">Pilih Program Studi</option>
        @foreach($studyPrograms as $program)
            <option value="{{ $program->id }}" @selected((string) old('study_program_id', $managedUser->study_program_id) === (string) $program->id)>{{ $program->name }} — {{ $program->faculty->name }}{{ $program->is_active && $program->faculty->is_active ? '' : ' (Unit Tidak Aktif - scope saat ini)' }}</option>
        @endforeach
    </select>
    @error('study_program_id')<p id="study_program_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<p class="form-help">Penugasan baru hanya untuk unit aktif. Scope lama yang nonaktif dapat dipertahankan dengan role yang sama.</p>
@foreach(['password' => 'Kata sandi', 'password_confirmation' => 'Konfirmasi kata sandi'] as $field => $label)
    <div class="form-field">
        <label for="{{ $field }}">{{ $label }} <span>{{ $managedUser->exists ? '(opsional)' : '(wajib)' }}</span></label>
        <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" @required(! $managedUser->exists)
            aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="password-help @error($field) {{ $field }}-error @enderror">
        @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endforeach
<p id="password-help" class="form-help">Gunakan minimal 12 karakter. {{ $managedUser->exists ? 'Kosongkan kata sandi untuk mempertahankan yang lama.' : '' }}</p>
<div class="form-field">
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox-label" for="is_active">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $managedUser->is_active))
            aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" @error('is_active') aria-describedby="is_active-error" @enderror>
        Status Aktif
    </label>
    @error('is_active')<p id="is_active-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
@if($managedUser->id === auth()->id())
    <p class="form-help">Akun sendiri harus tetap aktif dengan role Admin Universitas.</p>
@endif
