@extends('layouts.application')

@section('title', 'Edit Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => 'Edit Kurikulum']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">KURIKULUM PRODI</p><h1>Edit Kurikulum</h1><p>{{ $curriculum->name }}</p></header>
    <p class="dashboard-context">Program Studi {{ $studyProgram->name }}</p>
    <p>Status: <x-internal.status-badge :status="$curriculum->status" /></p><section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.curricula.update', $curriculum) }}" data-pending-form>
            @csrf
            @method('PUT')
            @include('admin.curricula.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button>
                <a href="{{ route('admin.curricula.index') }}">Kembali ke Kurikulum</a>
            </div>
        </form>
    </section>
@endsection

