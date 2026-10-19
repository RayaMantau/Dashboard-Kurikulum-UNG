@extends('layouts.application')

@section('title', 'Manajemen Pengguna')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Pengguna']];
@endphp

@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">AKUN INTERNAL</p><h1>Manajemen Pengguna</h1><p>Kelola akun, role, dan unit penugasan pengguna internal.</p></div>
        <a class="primary-button action-button" href="{{ route('university-admin.users.create') }}">Tambah Pengguna</a>
    </header>
    <section class="portal-card">
        <form method="GET" action="{{ route('university-admin.users.index') }}" class="management-filters">
            <div class="form-field">
                <label for="search">Cari pengguna</label>
                <input id="search" name="search" type="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau email">
            </div>
            <div class="form-field">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">Semua Role</option>
                    @foreach($roles as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="faculty_id">Fakultas</label>
                <select id="faculty_id" name="faculty_id">
                    <option value="">Semua Fakultas</option>
                    @foreach($faculties as $faculty)<option value="{{ $faculty->id }}" @selected((string) ($filters['faculty_id'] ?? '') === (string) $faculty->id)>{{ $faculty->name }}</option>@endforeach
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
            <a href="{{ route('university-admin.users.index') }}">Reset</a>
        </form>
        @if($users->isEmpty())
            <div class="dashboard-empty"><h2>Tidak ada pengguna yang sesuai dengan pencarian atau filter.</h2><p>Sesuaikan filter atau tambahkan pengguna internal.</p></div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar pengguna" tabindex="0">
                <table class="dashboard-table user-table">
                    <caption class="sr-only">Pengguna internal dan unit penugasan</caption>
                    <thead><tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Scope / Unit</th><th scope="col">Status</th><th scope="col">Login Terakhir</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <th scope="row">{{ $user->name }}</th>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role->label() }}</td>
                                <td>{{ match ($user->role->scopeField()) {
                                    'faculty_id' => $user->faculty?->name ?? 'Fakultas belum ditetapkan',
                                    'study_program_id' => $user->studyProgram?->name ?? 'Program Studi belum ditetapkan',
                                    default => 'Universitas Negeri Gorontalo',
                                } }}</td>
                                <td><span class="status-badge {{ $user->is_active ? 'status-published' : 'status-archived' }}">{{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                                <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah masuk' }}</td>
                                <td><a href="{{ route('university-admin.users.edit', $user) }}" aria-label="Edit {{ $user->name }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$users" />
    </section>
@endsection
