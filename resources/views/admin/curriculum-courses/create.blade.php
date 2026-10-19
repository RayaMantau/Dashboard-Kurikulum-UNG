@extends('layouts.application')
@section('title', 'Tambah Mata Kuliah ke Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Mata Kuliah', 'url' => route('admin.curricula.courses.index', $curriculum)], ['label' => 'Tambah']];
@endphp
@section('content')
    <header class="dashboard-heading"><h1>Tambah Mata Kuliah ke Kurikulum</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p></header>
    <p>Program Studi: {{ $curriculum->studyProgram->name }}</p>
    <section class="portal-card management-form">
        @if($semesters->isEmpty())
            <p>Tambahkan semester terlebih dahulu melalui <a href="{{ route('admin.curricula.semesters.create', $curriculum) }}">Tambah Semester</a>.</p>
        @elseif($courses->isEmpty())
            <p>Belum ada mata kuliah pada Program Studi ini. Tambahkan melalui <a href="{{ route('admin.courses.create') }}">Mata Kuliah</a>.</p>
        @else
            <form method="POST" action="{{ route('admin.curricula.courses.store', $curriculum) }}" data-pending-form>
                @csrf
                @include('admin.curriculum-courses.form')
                <div class="form-actions"><button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Mata Kuliah</button><a href="{{ route('admin.curricula.courses.index', $curriculum) }}">Batal</a></div>
            </form>
        @endif
    </section>
@endsection
