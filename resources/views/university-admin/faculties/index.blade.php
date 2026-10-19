@extends('layouts.application')

@section('title', 'Manajemen Fakultas')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Fakultas']];
@endphp

@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">MASTER DATA</p><h1>Manajemen Fakultas</h1><p>Kelola identitas dan status fakultas di Universitas Negeri Gorontalo.</p></div>
        <a class="primary-button action-button" href="{{ route('university-admin.faculties.create') }}">Tambah Fakultas</a>
    </header>
    <section class="portal-card">
        <form method="GET" action="{{ route('university-admin.faculties.index') }}" class="management-filters">
            <div class="form-field">
                <label for="search">Cari fakultas</label>
                <input id="search" type="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau kode fakultas">
            </div>
            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="1" @selected(($filters['status'] ?? '') === '1')>Aktif</option>
                    <option value="0" @selected(($filters['status'] ?? '') === '0')>Tidak Aktif</option>
                </select>
            </div>
            <button type="submit" class="primary-button action-button">Terapkan</button>
            <a href="{{ route('university-admin.faculties.index') }}">Reset</a>
        </form>
        @if($faculties->isEmpty())
            <div class="dashboard-empty">
                <h2>{{ filled($filters['search'] ?? null) || isset($filters['status']) ? 'Tidak ada fakultas yang sesuai.' : 'Belum ada data fakultas.' }}</h2>
                <p>Tambahkan fakultas baru atau sesuaikan pencarian dan filter Anda.</p>
            </div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar fakultas" tabindex="0">
                <table class="dashboard-table faculty-table">
                    <caption class="sr-only">Daftar fakultas dan jumlah program studi</caption>
                    <thead><tr><th scope="col">Nama Fakultas</th><th scope="col">Kode</th><th scope="col">Jumlah Program Studi</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($faculties as $faculty)
                            <tr>
                                <th scope="row">{{ $faculty->name }}</th>
                                <td>{{ $faculty->code }}</td>
                                <td>{{ $faculty->study_programs_count }}</td>
                                <td><span class="status-badge {{ $faculty->is_active ? 'status-published' : 'status-archived' }}">{{ $faculty->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                                <td><a href="{{ route('university-admin.faculties.edit', $faculty) }}" aria-label="Edit {{ $faculty->name }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$faculties" />
    </section>
@endsection
