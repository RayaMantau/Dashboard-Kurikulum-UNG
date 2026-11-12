@extends('layouts.application')
@section('title', 'Edit Semester')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Struktur Semester', 'url' => route('admin.curricula.semesters.index', $curriculum)], ['label' => 'Edit Semester']];
@endphp
@section('content')
    <header class="dashboard-heading"><h1>Edit Semester</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p><p>Rencana {{ $curriculum->total_semesters }} semester</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.curricula.semesters.update', [$curriculum, $semester]) }}" data-pending-form>
            @csrf @method('PUT')
            @include('admin.semesters.form')
            <div class="form-actions"><button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Semester</button><a href="{{ route('admin.curricula.semesters.index', $curriculum) }}">Batal</a></div>
        </form>
    </section>
    @can('update', $curriculum)
        <section class="portal-card danger-zone" aria-labelledby="semester-danger-title">
            <h2 id="semester-danger-title">Zona Berbahaya</h2>
            @if($semester->curriculum_courses_exists)
                <p>Semester tidak dapat dihapus karena masih memiliki mata kuliah.</p>
            @else
                <p>Penghapusan semester kosong tidak dapat dibatalkan. Pastikan semester ini tidak lagi diperlukan.</p>
                <details>
                    <summary>Konfirmasi penghapusan {{ $semester->name }}</summary>
                    <form method="POST" action="{{ route('admin.curricula.semesters.destroy', [$curriculum, $semester]) }}" data-pending-form>
                        @csrf @method('DELETE')
                        <label class="delete-confirmation"><input type="checkbox" required> Saya yakin ingin menghapus {{ $semester->name }}.</label>
                        <button type="submit" class="primary-button action-button danger-button" data-pending-label="Menghapus...">Hapus Semester</button>
                    </form>
                </details>
            @endif
        </section>
    @endcan
@endsection

