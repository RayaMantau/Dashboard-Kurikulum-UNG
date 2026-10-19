@extends('layouts.application')

@section('title', 'Unggah Dokumen Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Dokumen', 'url' => route('admin.curricula.documents.index', $curriculum)], ['label' => 'Unggah']];
@endphp

@section('content')
    <header class="dashboard-heading">
        <h1>Unggah Dokumen Kurikulum</h1>
        <p>{{ $curriculum->name }} ({{ $curriculum->year }})</p>
        <p>Program Studi {{ $curriculum->studyProgram->name }}</p>
    </header>
    <section class="portal-card management-form">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.curricula.documents.store', $curriculum) }}" data-pending-form>
            @csrf
            <div class="form-field">
                <label for="title">Judul Dokumen <span>(wajib)</span></label>
                <input type="text" id="title" name="title" required maxlength="255" value="{{ is_string(old('title')) ? old('title') : '' }}" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}">
                @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="form-field">
                <label for="file">File Dokumen <span>(wajib)</span></label>
                <input type="file" id="file" name="file" required accept=".pdf,application/pdf" aria-describedby="file-help" aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}" data-document-file>
                <p id="file-help">Format PDF. Maksimum 10 MB per file.</p>
                <p data-document-file-info aria-live="polite"></p>
                @error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Mengunggah...">Unggah Dokumen</button>
                <a href="{{ route('admin.curricula.documents.index', $curriculum) }}">Batal</a>
            </div>
        </form>
    </section>
@endsection
