@extends('layouts.application')

@section('title', 'Edit Program Studi')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Program Studi', 'url' => route('university-admin.study-programs.index')], ['label' => 'Edit Program Studi']];
@endphp

@section('content')
    <header class="dashboard-heading"><p class="eyebrow">MASTER DATA</p><h1>Edit Program Studi</h1><p>{{ $studyProgram->name }}</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('university-admin.study-programs.update', $studyProgram) }}" data-pending-form>
            @csrf
            @method('PUT')
            @include('university-admin.study-programs.form')
            <div class="form-actions">
                <button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Perubahan</button>
                <a href="{{ route('university-admin.study-programs.index') }}">Kembali ke Program Studi</a>
            </div>
        </form>
    </section>
@endsection
