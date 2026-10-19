@extends('layouts.application')

@section('title', 'Tambah Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => 'Tambah Kurikulum']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">KURIKULUM PRODI</p><h1>Tambah Kurikulum</h1><p>Masukkan informasi versi kurikulum baru. Kurikulum akan disimpan sebagai Draft.</p></header>
    <p class="dashboard-context">Program Studi {{ $studyProgram->name }}</p>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.curricula.store') }}" data-pending-form>
            @csrf
            @include('admin.curricula.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Kurikulum</button>
                <a href="{{ route('admin.curricula.index') }}">Batal</a>
            </div>
        </form>
    </section>
@endsection

