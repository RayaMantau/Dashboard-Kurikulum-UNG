@extends('layouts.application')

@section('title', 'Edit Mata Kuliah')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Mata Kuliah', 'url' => route('admin.courses.index')], ['label' => 'Edit Mata Kuliah']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MATA KULIAH PRODI</p><h1>Edit Mata Kuliah</h1><p>{{ $course->name }}</p></header>
    <p class="dashboard-context">Program Studi {{ $studyProgram->name }}</p>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}" data-pending-form>
            @csrf
            @method('PUT')
            @include('admin.courses.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button>
                <a class="primary-button action-button secondary-button" href="{{ route('admin.courses.index') }}">Batal</a>
            </div>
        </form>
    </section>
    @can('delete', $course)
        <section class="portal-card danger-zone" aria-labelledby="course-danger-title">
            <h2 id="course-danger-title">Zona Berbahaya</h2>
            <p>Penghapusan mata kuliah tidak dapat dibatalkan. Mata kuliah yang masih digunakan pada kurikulum tidak dapat dihapus.</p>
            <details>
                <summary>Konfirmasi penghapusan {{ $course->name }}</summary>
                <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" data-pending-form>
                    @csrf @method('DELETE')
                    <label class="delete-confirmation"><input type="checkbox" required> Saya yakin ingin menghapus {{ $course->name }}.</label>
                    <button type="submit" class="primary-button action-button danger-button" data-pending-label="Menghapus...">Hapus Mata Kuliah</button>
                </form>
            </details>
        </section>
    @endcan
@endsection
