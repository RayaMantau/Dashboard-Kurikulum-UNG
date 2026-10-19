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
        <p><a href="{{ route('internal.curricula.history', $curriculum) }}">Riwayat Kurikulum</a></p>
        <x-internal.status-badge :status="$curriculum->status" />
    </header>
    <section class="portal-card dashboard-section">
        <h2>Informasi Kurikulum</h2>
        <dl>
            <dt>Tahun</dt><dd>{{ $curriculum->year }}</dd>
            <dt>Tahun Berlaku</dt><dd>{{ $curriculum->effective_year ?? '-' }}</dd>
            <dt>Total SKS</dt><dd>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</dd>
            <dt>Jumlah Semester</dt><dd>{{ $curriculum->total_semesters }}</dd>
            <dt>Diajukan</dt><dd>{{ $curriculum->submitted_at?->format('d/m/Y H:i') ?? 'Waktu pengajuan belum tercatat' }}</dd>
        </dl>
        @if($curriculum->description)<p>{{ $curriculum->description }}</p>@endif
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
                    <a href="{{ route('kaprodi.submissions.documents.download', [$curriculum, $document]) }}">Unduh</a>
                </li>
            @empty
                <li class="dashboard-empty">Belum ada dokumen pada kurikulum ini.</li>
            @endforelse
        </ul>
    </section>
    @can('review', $curriculum)
        <section class="portal-card dashboard-section">
            <h2>Keputusan Review</h2>
            <details>
                <summary>Setujui Kurikulum</summary>
                <p>Setujui kurikulum ini? Kurikulum akan berstatus Disetujui dan menunggu proses publikasi.</p>
                <form method="POST" action="{{ route('kaprodi.submissions.approve', $curriculum) }}" data-pending-form>
                    @csrf
                    <button type="submit" class="primary-button action-button success-button" data-pending-label="Menyetujui...">Konfirmasi Persetujuan</button>
                </form>
            </details>
            <h3>Minta Revisi</h3>
            <form method="POST" action="{{ route('kaprodi.submissions.revision', $curriculum) }}" data-pending-form>
                @csrf
                <div class="form-field">
                    <label for="notes">Catatan Revisi <span>(wajib)</span></label>
                    <textarea id="notes" name="notes" rows="5" required maxlength="10000" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" @error('notes') aria-describedby="notes-error" @enderror>{{ is_string(old('notes')) ? old('notes') : '' }}</textarea>
                    @error('notes')<p id="notes-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="primary-button action-button warning-button" data-pending-label="Mengirim...">Minta Revisi</button>
            </form>
        </section>
    @endcan
    <p><a href="{{ route('kaprodi.submissions.index') }}">Kembali ke Pengajuan</a></p>
@endsection
