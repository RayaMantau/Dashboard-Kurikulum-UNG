@extends('layouts.application')

@section('title', 'Tambah Pengguna')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Pengguna', 'url' => route('university-admin.users.index')], ['label' => 'Tambah Pengguna']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Tambah Pengguna</h1><p>Masukkan identitas Pengguna. Pilih role dan unit penugasan akun internal.</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.users.store') }}" data-pending-form>
            @csrf
            @include('university-admin.users.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Pengguna</button>
                <a href="{{ route('university-admin.users.index') }}">Batal</a>
            </div>
        </form>
    </section>
@endsection
