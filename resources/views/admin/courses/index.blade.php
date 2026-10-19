@extends('layouts.application')

@section('title', 'Mata Kuliah')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Mata Kuliah']];
@endphp

@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">MATA KULIAH PRODI</p><h1>Mata Kuliah</h1><p>Program Studi {{ $studyProgram->name }}</p></div>
        <a class="primary-button action-button" href="{{ route('admin.courses.create') }}">Tambah Mata Kuliah</a>
    </header>
    <section class="portal-card">
        <form method="GET" action="{{ route('admin.courses.index') }}" class="management-filters">
            <div class="form-field">
                <label for="search">Cari mata kuliah</label>
                <input id="search" type="search" name="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Kode atau nama mata kuliah">
            </div>
            <button type="submit" class="primary-button action-button">Cari</button>
            <a href="{{ route('admin.courses.index') }}">Reset</a>
        </form>
        @if($courses->isEmpty())
            <div class="dashboard-empty">
                <h2>{{ filled($filters['search'] ?? null) ? 'Tidak ada mata kuliah yang sesuai dengan pencarian.' : 'Belum ada mata kuliah pada Program Studi ini.' }}</h2>
                <p>Tambahkan mata kuliah baru atau sesuaikan pencarian Anda.</p>
            </div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar mata kuliah" tabindex="0">
                <table class="dashboard-table course-table">
                    <caption class="sr-only">Mata kuliah Program Studi {{ $studyProgram->name }}</caption>
                    <thead><tr><th scope="col">Kode</th><th scope="col">Nama Mata Kuliah</th><th scope="col">Deskripsi</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($courses as $course)
                            <tr>
                                <td>{{ $course->code }}</td>
                                <th scope="row">{{ $course->name }}</th>
                                <td>{{ \Illuminate\Support\Str::limit($course->description ?? '-', 150) }}</td>
                                <td><a href="{{ route('admin.courses.edit', $course) }}" aria-label="Edit {{ $course->name }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$courses" />
    </section>
@endsection
