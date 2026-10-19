@extends('layouts.application')
@section('title', 'Mata Kuliah Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Mata Kuliah']];
@endphp
@section('content')
    <header class="management-heading">
        <div><p class="eyebrow">STRUKTUR KURIKULUM</p><h1>Mata Kuliah Kurikulum</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p><x-internal.status-badge :status="$curriculum->status" /></div>
        @can('update', $curriculum)<a class="primary-button action-button" href="{{ route('admin.curricula.courses.create', $curriculum) }}">Tambah Mata Kuliah ke Kurikulum</a>@endcan
    </header>
    <p>Program Studi: {{ $curriculum->studyProgram->name }}</p>
    <p><a href="{{ route('admin.curricula.semesters.index', $curriculum) }}">Struktur Semester</a></p>
    @forelse($semesters as $semester)
        <section class="portal-card">
            <h2>{{ $semester->name }}</h2>
            @if($semester->curriculumCourses->isEmpty())
                <p class="dashboard-empty">Belum ada mata kuliah.</p>
            @else
                <div class="dashboard-table-wrap" role="region" aria-label="Mata kuliah {{ $semester->name }}" tabindex="0">
                    <table class="dashboard-table course-table">
                        <thead><tr><th scope="col">Kode</th><th scope="col">Mata Kuliah</th><th scope="col">SKS</th><th scope="col">Jenis</th><th scope="col">Prasyarat</th><th scope="col">Aksi</th></tr></thead>
                        <tbody>
                        @foreach($semester->curriculumCourses as $assignment)
                            <tr>
                                <td>{{ $assignment->course_code }}</td><th scope="row">{{ $assignment->course_name }}</th>
                                <td>{{ number_format((float) $assignment->sks, 2, ',', '.') }}</td><td>{{ $assignment->course_type->label() }}</td>
                                <td>{{ $assignment->prerequisite_notes ?? '-' }}</td>
                                <td>
                                    @can('update', $curriculum)
                                        <a href="{{ route('admin.curricula.courses.edit', [$curriculum, $assignment]) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin.curricula.courses.destroy', [$curriculum, $assignment]) }}" data-pending-form>
                                            @csrf @method('DELETE')
                                            <button type="submit" class="primary-button action-button danger-button" data-pending-label="Menghapus..." aria-label="Hapus {{ $assignment->course_name }} dari kurikulum">Hapus dari Kurikulum</button>
                                        </form>
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
    @empty
        <section class="portal-card"><p class="dashboard-empty">Belum ada semester pada kurikulum ini.</p></section>
    @endforelse
@endsection
