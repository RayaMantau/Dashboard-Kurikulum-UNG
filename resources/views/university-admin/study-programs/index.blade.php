@extends('layouts.application')

@section('title', 'Manajemen Program Studi')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Program Studi']];
@endphp

@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">MASTER DATA</p><h1>Manajemen Program Studi</h1><p>Kelola identitas dan status Program Studi di Universitas Negeri Gorontalo.</p></div>
        <a class="primary-button action-button" href="{{ route('university-admin.study-programs.create') }}">Tambah Program Studi</a>
    </header>
    <section class="portal-card">
        <form method="GET" action="{{ route('university-admin.study-programs.index') }}" class="management-filters">
            <div class="form-field">
                <label for="search">Cari Program Studi</label>
                <input id="search" type="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, kode, atau fakultas">
            </div>
            <div class="form-field">
                <label for="faculty_id">Fakultas</label>
                <select id="faculty_id" name="faculty_id">
                    <option value="">Semua Fakultas</option>
                    @foreach($faculties as $faculty)
                        <option value="{{ $faculty->id }}" @selected((string) ($filters['faculty_id'] ?? '') === (string) $faculty->id)>{{ $faculty->name }}</option>
                    @endforeach
                </select>
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
            <a href="{{ route('university-admin.study-programs.index') }}">Reset</a>
        </form>
        @if($studyPrograms->isEmpty())
            <div class="dashboard-empty">
                <h2>{{ filled($filters['search'] ?? null) || isset($filters['status']) || isset($filters['faculty_id']) ? 'Tidak ada Program Studi yang sesuai dengan pencarian atau filter.' : 'Belum ada Program Studi.' }}</h2>
                <p>Tambahkan Program Studi baru atau sesuaikan pencarian dan filter Anda.</p>
            </div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar Program Studi" tabindex="0">
                <table class="dashboard-table study-program-table">
                    <caption class="sr-only">Daftar Program Studi dan fakultas</caption>
                    <thead><tr><th scope="col">Program Studi</th><th scope="col">Kode</th><th scope="col">Jenjang</th><th scope="col">Fakultas</th><th scope="col">Akreditasi</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($studyPrograms as $studyProgram)
                            <tr>
                                <th scope="row">{{ $studyProgram->name }}</th>
                                <td>{{ $studyProgram->code }}</td>
                                <td>{{ $studyProgram->degree }}</td>
                                <td>{{ $studyProgram->faculty->name }}</td>
                                <td>{{ $studyProgram->accreditation ?? '-' }}</td>
                                <td><span class="status-badge {{ $studyProgram->is_active ? 'status-published' : 'status-archived' }}">{{ $studyProgram->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                                <td><a href="{{ route('university-admin.study-programs.edit', $studyProgram) }}" aria-label="Edit {{ $studyProgram->name }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$studyPrograms" />
    </section>
@endsection
