@extends('layouts.application')

@section('title', 'Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum']];
@endphp

@section('content')
    <header class="management-heading">
        <div>
            <p class="eyebrow">VERSI KURIKULUM</p>
            <h1>Kurikulum</h1>
            <p>Program Studi {{ $studyProgram->name }}</p>
            <p class="dashboard-muted">Daftar versi kurikulum dari tahun terbaru. Status Dipublikasikan menandai kurikulum yang berlaku saat ini.</p>
        </div>
        @can('create', \App\Models\Curriculum::class)
            <a class="primary-button action-button" href="{{ route('admin.curricula.create') }}">Tambah Kurikulum</a>
        @endcan
    </header>
    <section class="portal-card">
        <form method="GET" action="{{ route('admin.curricula.index') }}" class="management-filters">
            <div class="form-field">
                <label for="search">Cari kurikulum</label>
                <input id="search" type="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau tahun kurikulum">
            </div>
            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="year">Tahun</label>
                <select id="year" name="year">
                    <option value="">Semua Tahun</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="primary-button action-button">Terapkan</button>
            <a href="{{ route('admin.curricula.index') }}">Reset</a>
        </form>
        @if($curricula->isEmpty())
            <div class="dashboard-empty">
                <h2>{{ filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['year'] ?? null) ? 'Tidak ada kurikulum yang sesuai dengan pencarian atau filter.' : 'Belum ada kurikulum pada Program Studi ini.' }}</h2>
            </div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar versi kurikulum" tabindex="0">
                <table class="dashboard-table curriculum-table">
                    <caption class="sr-only">Versi kurikulum Program Studi {{ $studyProgram->name }}</caption>
                    <thead><tr><th scope="col">Nama Kurikulum</th><th scope="col">Tahun</th><th scope="col">Tahun Berlaku</th><th scope="col">Total SKS</th><th scope="col">Jumlah Semester</th><th scope="col">Status</th><th scope="col">Terakhir Diperbarui</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($curricula as $curriculum)
                            <tr>
                                <th scope="row">{{ $curriculum->name }}</th>
                                <td>{{ $curriculum->year }}</td>
                                <td>{{ $curriculum->effective_year ?? '-' }}</td>
                                <td>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</td>
                                <td>{{ $curriculum->total_semesters }}</td>
                                <td><x-internal.status-badge :status="$curriculum->status" /></td>
                                <td>{{ $curriculum->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td><a href="{{ route('internal.curricula.history', $curriculum) }}">Riwayat</a> <a href="{{ route('admin.curricula.semesters.index', $curriculum) }}">Semester</a> <a href="{{ route('admin.curricula.courses.index', $curriculum) }}">Mata Kuliah</a> <a href="{{ route('admin.curricula.documents.index', $curriculum) }}">Dokumen</a> @can('submit', $curriculum)<a href="{{ route('admin.curricula.submit.confirm', $curriculum) }}">Ajukan Review</a>@endcan @can('update', $curriculum)<a href="{{ route('admin.curricula.edit', $curriculum) }}" aria-label="Edit {{ $curriculum->name }}">Edit</a>@else<span class="dashboard-muted">Terkunci</span>@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$curricula" />
    </section>
@endsection
