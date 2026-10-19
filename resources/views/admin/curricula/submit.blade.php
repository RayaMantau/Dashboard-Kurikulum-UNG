@extends('layouts.application')

@section('title', 'Ajukan Kurikulum untuk Review')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Ajukan Review']];
    $repairLinks = [
        'metadata' => ['admin.curricula.edit', 'Edit Kurikulum'],
        'semesters' => ['admin.curricula.semesters.index', 'Kelola Semester'],
        'courses' => ['admin.curricula.courses.index', 'Kelola Mata Kuliah'],
        'documents' => ['admin.curricula.documents.index', 'Kelola Dokumen'],
    ];
@endphp

@section('content')
    <header class="dashboard-heading">
        <h1>Ajukan Kurikulum untuk Review</h1>
        <p>Program Studi {{ $studyProgram->name }}</p>
        <p>{{ $curriculum->name }} ({{ $curriculum->year }})</p>
        <x-internal.status-badge :status="$curriculum->status" />
    </header>
    <section class="portal-card">
        <h2>Kesiapan Kurikulum</h2>
        <ul class="dashboard-records">
            @foreach($readiness['checks'] as $key => $check)
                <li>
                    <h3>{{ $check['label'] }}: {{ $check['passed'] ? 'Lengkap' : 'Belum lengkap' }}</h3>
                    @if($check['passed'] || $key === 'semesters')
                        <p>{{ $check['message'] }}</p>
                    @endif
                    @foreach($check['errors'] as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                    @if(!$check['passed'])
                        @can('update', $curriculum)
                            <a href="{{ route($repairLinks[$key][0], $curriculum) }}">{{ $repairLinks[$key][1] }}</a>
                        @endcan
                    @endif
                </li>
            @endforeach
        </ul>
        <p>Setelah diajukan, status menjadi Menunggu Review. Metadata, semester, mata kuliah, dan dokumen tidak dapat diedit sampai Kaprodi mengembalikan kurikulum untuk revisi. Pengajuan tidak berarti persetujuan otomatis.</p>
        @if($readiness['is_ready'])
            <form method="POST" action="{{ route('admin.curricula.submit.store', $curriculum) }}" data-pending-form>
                @csrf
                <button type="submit" class="primary-button action-button" data-pending-label="Mengajukan...">Ajukan untuk Review</button>
            </form>
        @else
            <p class="dashboard-muted">Lengkapi persyaratan di atas sebelum mengajukan kurikulum.</p>
        @endif
        <p><a href="{{ route('admin.curricula.index') }}">Kembali ke Kurikulum</a></p>
    </section>
@endsection
