@extends('layouts.application')

@section('title', 'Edit Fakultas')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Fakultas', 'url' => route('university-admin.faculties.index')], ['label' => 'Edit Fakultas']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Edit Fakultas</h1><p>{{ $faculty->name }}</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.faculties.update', $faculty) }}" data-pending-form>
            @csrf
            @method('PUT')
            @include('university-admin.faculties.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button>
                <a href="{{ route('university-admin.faculties.index') }}">Kembali ke Fakultas</a>
            </div>
        </form>
    </section>
@endsection
