@extends('layouts.application')
@section('title', 'Struktur Semester')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Struktur Semester']];
@endphp
@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">STRUKTUR KURIKULUM</p><h1>Struktur Semester</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p><p>Rencana {{ $curriculum->total_semesters }} semester</p><x-internal.status-badge :status="$curriculum->status" /></div>
        @can('update', $curriculum)<a class="primary-button action-button" href="{{ route('admin.curricula.semesters.create', $curriculum) }}">Tambah Semester</a>@endcan
    </header>
    <p><a href="{{ route('admin.curricula.courses.index', $curriculum) }}">Mata Kuliah Kurikulum</a></p>
    <section class="portal-card">
        @if($semesters->isEmpty())
            <div class="dashboard-empty"><h2>Belum ada semester pada kurikulum ini.</h2></div>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Daftar semester" tabindex="0">
                <table class="dashboard-table">
                    <thead><tr><th scope="col">Semester</th><th scope="col">Nama</th><th scope="col">Total SKS</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                    @foreach($semesters as $semester)
                        <tr>
                            <td>{{ $semester->semester_number }}</td><th scope="row">{{ $semester->name }}</th><td>{{ number_format((float) $semester->total_sks, 2, ',', '.') }}</td>
                            <td>
                                @can('update', $curriculum)
                                    <a href="{{ route('admin.curricula.semesters.edit', [$curriculum, $semester]) }}">Edit</a>
                                    @if(!$semester->curriculum_courses_exists)
                                        <form method="POST" action="{{ route('admin.curricula.semesters.destroy', [$curriculum, $semester]) }}" data-pending-form>
                                            @csrf @method('DELETE')
                                            <button type="submit" class="primary-button action-button danger-button" data-pending-label="Menghapus..." aria-label="Hapus {{ $semester->name }}">Hapus Semester</button>
                                        </form>
                                    @else
                                        <span class="dashboard-muted">Berisi mata kuliah</span>
                                    @endif
                                @else
                                    <span class="dashboard-muted">Terkunci</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
