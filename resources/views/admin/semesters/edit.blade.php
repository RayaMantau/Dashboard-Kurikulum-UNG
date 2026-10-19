@extends('layouts.application')
@section('title', 'Edit Semester')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Struktur Semester', 'url' => route('admin.curricula.semesters.index', $curriculum)], ['label' => 'Edit Semester']];
@endphp
@section('content')
    <header class="dashboard-heading"><h1>Edit Semester</h1><p>{{ $curriculum->name }} ({{ $curriculum->year }})</p><p>Rencana {{ $curriculum->total_semesters }} semester</p></header>
    <section class="portal-card management-form">
        <form method="POST" action="{{ route('admin.curricula.semesters.update', [$curriculum, $semester]) }}" data-pending-form>
            @csrf @method('PUT')
            @include('admin.semesters.form')
            <div class="form-actions"><button type="submit" class="primary-button action-button" data-pending-label="Menyimpan...">Simpan Semester</button><a href="{{ route('admin.curricula.semesters.index', $curriculum) }}">Batal</a></div>
        </form>
    </section>
@endsection

