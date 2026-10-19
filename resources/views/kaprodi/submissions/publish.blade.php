@extends('layouts.application')
@section('title', 'Publikasikan Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Publikasi Kurikulum', 'url' => route('kaprodi.submissions.approved')], ['label' => 'Publikasikan Kurikulum']];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Publikasikan Kurikulum</h1>
        <p>Program Studi {{ $studyProgram->name }}</p>
    </header>
    <section class="portal-card dashboard-section">
        <h2>{{ $curriculum->name }}</h2>
        <x-internal.status-badge :status="$curriculum->status" />
        <dl>
            <dt>Tahun</dt><dd>{{ $curriculum->year }}</dd>
            <dt>Disetujui</dt><dd>{{ $curriculum->approved_at?->format('d/m/Y H:i') ?? 'Waktu persetujuan belum tercatat' }}</dd>
            <dt>Total SKS</dt><dd>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</dd>
            <dt>Jumlah Semester</dt><dd>{{ $curriculum->total_semesters }}</dd>
        </dl>
    </section>
    <section class="portal-card dashboard-section">
        <h2>Dampak Publikasi</h2>
        <p><strong>{{ $curriculum->name }}</strong> akan menjadi kurikulum aktif berstatus Dipublikasikan pada Program Studi ini.</p>
        @forelse($previousCurricula as $previous)
            <p>Kurikulum yang saat ini aktif: <strong>{{ $previous->name }}</strong> ({{ $previous->year }}).</p>
            <p><x-internal.status-badge :status="$previous->status" /> &rarr; Diarsipkan</p>
        @empty
            <p>Belum ada kurikulum aktif yang akan diarsipkan.</p>
        @endforelse
        <p>Kurikulum yang dipublikasikan dan diarsipkan tetap hanya dapat dibaca. Struktur, dokumen, dan riwayat versi sebelumnya tetap disimpan.</p>
        <p>Data kurikulum aktif akan diperiksa ulang saat publikasi diproses.</p>
        <form method="POST" action="{{ route('kaprodi.submissions.publish.store', $curriculum) }}" data-pending-form>
            @csrf
            <button type="submit" class="primary-button action-button" data-pending-label="Mempublikasikan...">Publikasikan Kurikulum</button>
        </form>
    </section>
    <p><a href="{{ route('kaprodi.submissions.approved') }}">Batal dan kembali ke daftar publikasi</a></p>
@endsection
