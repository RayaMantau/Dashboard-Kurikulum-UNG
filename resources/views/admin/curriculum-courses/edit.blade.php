@extends('layouts.application')
@section('title', 'Edit Mata Kuliah Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Mata Kuliah', 'url' => route('admin.curricula.courses.index', $curriculum)], ['label' => 'Edit']];
@endphp
@section('content')
    <header class="dashboard-heading"><h1>Edit Mata Kuliah Kurikulum</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p><p>{{ $assignment->course_code }} — {{ $assignment->course_name }}</p></header>
    <p>Program Studi: {{ $curriculum->studyProgram->name }}</p>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.curricula.courses.update', [$curriculum, $assignment]) }}" data-pending-form>
            @csrf @method('PUT')
            @include('admin.curriculum-courses.form')
            <div class="form-actions"><button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button><a href="{{ route('admin.curricula.courses.index', $curriculum) }}">Batal</a></div>
        </form>
    </section>
@endsection
