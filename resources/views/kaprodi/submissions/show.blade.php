@extends('layouts.application')
@section('title', 'Review Pengajuan Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Pengajuan Kurikulum', 'url' => route('kaprodi.submissions.index')], ['label' => $curriculum->name]];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Review Pengajuan Kurikulum</h1>
        <p>Program Studi {{ $curriculum->studyProgram->name }}</p>
        <h2>{{ $curriculum->name }}</h2>
        <p><a class="primary-button action-button secondary-button" href="{{ route('internal.curricula.history', $curriculum) }}">Riwayat Kurikulum</a></p>
        <x-internal.status-badge :status="$curriculum->status" />
    </header>
    <section class="portal-card dashboard-section">
        <h2>Informasi Kurikulum</h2>
        <div class="dashboard-table-wrap" role="region" aria-label="Informasi Kurikulum" tabindex="0">
            <table class="dashboard-table review-information">
                <caption class="sr-only">Informasi Kurikulum {{ $curriculum->name }}</caption>
                <thead><tr><th scope="col">Label</th><th scope="col">Nilai</th></tr></thead>
                <tbody>
                    <tr><th scope="row">Tahun</th><td>{{ $curriculum->year }}</td></tr>
                    <tr><th scope="row">Tahun Berlaku</th><td>{{ $curriculum->effective_year ?? '-' }}</td></tr>
                    <tr><th scope="row">Total SKS</th><td>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</td></tr>
                    <tr><th scope="row">Jumlah Semester</th><td>{{ $curriculum->total_semesters }}</td></tr>
                    <tr><th scope="row">Diajukan</th><td>{{ $curriculum->submitted_at?->format('d/m/Y H:i') ?? 'Waktu pengajuan belum tercatat' }}</td></tr>
                    @if($curriculum->description)<tr><th scope="row">Deskripsi</th><td>{{ $curriculum->description }}</td></tr>@endif
                </tbody>
            </table>
        </div>
    </section>
    @forelse($curriculum->semesters as $semester)
        <section class="portal-card dashboard-section">
            <h2>{{ $semester->name }}</h2>
            <p>Semester {{ $semester->semester_number }} &middot; Total SKS: {{ number_format((float) $semester->total_sks, 2, ',', '.') }}</p>
            @if($semester->curriculumCourses->isEmpty())
                <p class="dashboard-empty">Belum ada mata kuliah pada semester ini.</p>
            @else
                <div class="dashboard-table-wrap" role="region" aria-label="Mata kuliah {{ $semester->name }}" tabindex="0">
                    <table class="dashboard-table">
                        <thead><tr><th scope="col">Kode</th><th scope="col">Mata Kuliah</th><th scope="col">SKS</th><th scope="col">Jenis</th><th scope="col">Catatan Prasyarat</th></tr></thead>
                        <tbody>
                            @foreach($semester->curriculumCourses as $assignment)
                                <tr>
                                    <td>{{ $assignment->course_code }}</td><th scope="row">{{ $assignment->course_name }}</th>
                                    <td>{{ number_format((float) $assignment->sks, 2, ',', '.') }}</td>
                                    <td>{{ $assignment->course_type->label() }}</td>
                                    <td>{{ $assignment->prerequisite_notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <section class="portal-card"><p class="dashboard-empty">Belum ada semester pada kurikulum ini.</p></section>
    @endforelse
    <section class="portal-card dashboard-section">
        <h2>Dokumen Kurikulum</h2>
        <ul class="dashboard-records">
            @forelse($curriculum->documents as $document)
                <li>
                    <h3>{{ $document->title }}</h3>
                    <p>{{ $document->file_name }} &middot; {{ $document->file_type }}</p>
                    <p>{{ $document->file_size === null ? 'Ukuran belum tercatat' : number_format($document->file_size / 1024, 2, ',', '.').' KB' }}
                        &middot; {{ $document->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                    <a class="primary-button action-button secondary-button" href="{{ route('kaprodi.submissions.documents.download', [$curriculum, $document]) }}">Unduh</a>
                </li>
            @empty
                <li class="dashboard-empty">Belum ada dokumen pada kurikulum ini.</li>
            @endforelse
        </ul>
    </section>
    @can('review', $curriculum)
        <section class="portal-card dashboard-section">
            <h2>Keputusan Review</h2>
            <h3>Konfirmasi Persetujuan</h3>
            <form id="approve-curriculum" method="POST" action="{{ route('kaprodi.submissions.approve', $curriculum) }}" data-pending-form>
                @csrf
                <p>Kurikulum yang disetujui akan menunggu proses publikasi.</p>
                <label class="checkbox-label"><input type="checkbox" required> Saya telah meninjau dan menyetujui kurikulum ini.</label>
            </form>
            <h3>Minta Revisi</h3>
            <form id="revise-curriculum" method="POST" action="{{ route('kaprodi.submissions.revision', $curriculum) }}" data-pending-form>
                @csrf
                <div class="form-field">
                    <label for="notes">Catatan Revisi <span>(wajib)</span></label>
                    <textarea id="notes" name="notes" rows="5" required maxlength="10000" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" @error('notes') aria-describedby="notes-error" @enderror>{{ is_string(old('notes')) ? old('notes') : '' }}</textarea>
                    @error('notes')<p id="notes-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </form>
        </section>
    @endcan
    <div class="review-actions" role="group" aria-label="Tindakan review kurikulum">
        @can('review', $curriculum)
            <button type="submit" form="approve-curriculum" class="primary-button action-button success-button" data-pending-label="Menyetujui...">Setujui Kurikulum</button>
            <button type="submit" form="revise-curriculum" class="primary-button action-button warning-button" data-pending-label="Mengirim...">Minta Revisi</button>
        @endcan
        <a class="primary-button action-button secondary-button" href="{{ route('kaprodi.submissions.index') }}">Batal</a>
    </div>
@endsection
