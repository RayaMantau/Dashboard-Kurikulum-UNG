@extends('layouts.application')

@section('title', 'Tambah Fakultas')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Fakultas', 'url' => route('university-admin.faculties.index')], ['label' => 'Tambah Fakultas']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Tambah Fakultas</h1><p>Masukkan identitas fakultas. Nama dan kode wajib diisi.</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.faculties.store') }}" data-pending-form>
            @csrf
            @include('university-admin.faculties.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Fakultas</button>
                <a href="{{ route('university-admin.faculties.index') }}">Batal</a>
            </div>
        </form>
    </section>
@endsection
