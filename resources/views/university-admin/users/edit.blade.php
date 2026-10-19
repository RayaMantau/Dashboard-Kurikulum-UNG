@extends('layouts.application')

@section('title', 'Edit Pengguna')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Pengguna', 'url' => route('university-admin.users.index')], ['label' => 'Edit Pengguna']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Edit Pengguna</h1><p>{{ $managedUser->name }}</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.users.update', $managedUser) }}" data-pending-form>
            @csrf
            @method('PUT')
            @include('university-admin.users.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button>
                <a href="{{ route('university-admin.users.index') }}">Kembali ke Pengguna</a>
            </div>
        </form>
    </section>
@endsection
