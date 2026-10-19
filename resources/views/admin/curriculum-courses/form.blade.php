<div class="form-field">
    <label for="semester_id">Semester <span>(wajib)</span></label>
    <select id="semester_id" name="semester_id" required aria-invalid="{{ $errors->has('semester_id') ? 'true' : 'false' }}" @error('semester_id') aria-describedby="semester_id-error" @enderror>
        <option value="">Pilih Semester</option>
        @foreach($semesters as $semester)
            <option value="{{ $semester->id }}" @selected((string) old('semester_id', $assignment->semester_id) === (string) $semester->id)>{{ $semester->name }}</option>
        @endforeach
    </select>
    @error('semester_id')<p id="semester_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
@if(!$assignment->exists)
    <div class="form-field">
        <label for="course_id">Mata Kuliah <span>(wajib)</span></label>
        <select id="course_id" name="course_id" required aria-invalid="{{ $errors->has('course_id') ? 'true' : 'false' }}" @error('course_id') aria-describedby="course_id-error" @enderror>
            <option value="">Pilih Mata Kuliah</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->code }} — {{ $course->name }}</option>
            @endforeach
        </select>
        @error('course_id')<p id="course_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endif
<div class="form-field">
    <label for="sks">SKS <span>(wajib)</span></label>
    <input id="sks" name="sks" type="number" required min="0" max="99.99" step="0.01" value="{{ old('sks', $assignment->sks) }}" aria-invalid="{{ $errors->has('sks') ? 'true' : 'false' }}" @error('sks') aria-describedby="sks-error" @enderror>
    @error('sks')<p id="sks-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="course_type">Jenis Mata Kuliah <span>(wajib)</span></label>
    <select id="course_type" name="course_type" required aria-invalid="{{ $errors->has('course_type') ? 'true' : 'false' }}" @error('course_type') aria-describedby="course_type-error" @enderror>
        <option value="">Pilih Jenis</option>
        @foreach($types as $type)
            <option value="{{ $type->value }}" @selected(old('course_type', $assignment->course_type?->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </select>
    @error('course_type')<p id="course_type-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-field">
    <label for="prerequisite_notes">Catatan Prasyarat <span>(opsional)</span></label>
    <textarea id="prerequisite_notes" name="prerequisite_notes" rows="5" maxlength="10000" aria-invalid="{{ $errors->has('prerequisite_notes') ? 'true' : 'false' }}" @error('prerequisite_notes') aria-describedby="prerequisite_notes-error" @enderror>{{ is_string(old('prerequisite_notes', $assignment->prerequisite_notes)) ? old('prerequisite_notes', $assignment->prerequisite_notes) : '' }}</textarea>
    @error('prerequisite_notes')<p id="prerequisite_notes-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
