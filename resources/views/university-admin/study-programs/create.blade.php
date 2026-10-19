@extends('layouts.application')

@section('title', 'Tambah Program Studi')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Program Studi', 'url' => route('university-admin.study-programs.index')], ['label' => 'Tambah Program Studi']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Tambah Program Studi</h1><p>Masukkan identitas Program Studi. Pilih fakultas serta isi nama, kode, dan jenjang.</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.study-programs.store') }}" data-pending-form>
            @csrf
            @include('university-admin.study-programs.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Program Studi</button>
                <a href="{{ route('university-admin.study-programs.index') }}">Batal</a>
            </div>
        </form>
    </section>
@endsection
