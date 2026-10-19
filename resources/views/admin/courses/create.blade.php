@extends('layouts.application')

@section('title', 'Tambah Mata Kuliah')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Mata Kuliah', 'url' => route('admin.courses.index')], ['label' => 'Tambah Mata Kuliah']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MATA KULIAH PRODI</p><h1>Tambah Mata Kuliah</h1><p>Masukkan identitas mata kuliah. Nama dan kode wajib diisi.</p></header>
    <p class="dashboard-context">Program Studi {{ $studyProgram->name }}</p>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.courses.store') }}" data-pending-form>
            @csrf
            @include('admin.courses.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Mata Kuliah</button>
                <a href="{{ route('admin.courses.index') }}">Batal</a>
            </div>
        </form>
    </section>
@endsection
