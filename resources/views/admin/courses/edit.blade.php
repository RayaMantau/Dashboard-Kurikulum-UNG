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
                <a href="{{ route('admin.courses.index') }}">Kembali ke Mata Kuliah</a>
            </div>
        </form>
    </section>
@endsection
