@extends('layouts.application')

@section('title', 'Dashboard '.$role->label())

@section('content')
    <header class="dashboard-heading">
        <p class="eyebrow">RINGKASAN KURIKULUM</p>
        <h1>Dashboard {{ $role->label() }}</h1>
        <p>Selamat datang, <strong>{{ auth()->user()->name }}</strong>.</p>
        <p class="dashboard-context">{{ $scopeName }}</p>
    </header>

    @if($missingScope)
        <div class="shell-alert" role="status">Penugasan Anda belum lengkap. Hubungi administrator untuk menetapkan fakultas atau program studi. Data dashboard belum tersedia.</div>
    @endif

    <section class="dashboard-stats" aria-label="Statistik ringkas">
        @foreach($metrics as $label => $value)
            <x-internal.stat-card :label="$label" :value="$value" />
        @endforeach
    </section>

    @switch($role)
        @case(\App\Enums\UserRole::UNIVERSITY_ADMIN)
            @include('admin.dashboards.university')
            @break
        @case(\App\Enums\UserRole::FACULTY_ADMIN)
            @include('admin.dashboards.faculty')
            @break
        @case(\App\Enums\UserRole::STUDY_PROGRAM_ADMIN)
            @include('admin.dashboards.study-program')
            @break
        @case(\App\Enums\UserRole::HEAD_OF_STUDY_PROGRAM)
            @include('admin.dashboards.head')
            @break
    @endswitch
@endsection
