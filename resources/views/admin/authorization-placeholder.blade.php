@extends('layouts.application')

@php
    $area = match (true) {
        request()->routeIs('university-admin.*') => ['label' => 'Administrasi Universitas', 'url' => route('university-admin.dashboard')],
        request()->routeIs('faculty-admin.*') => ['label' => 'Monitoring Fakultas', 'url' => route('faculty-admin.dashboard')],
        request()->routeIs('kaprodi.*') => ['label' => 'Review Kurikulum', 'url' => route('kaprodi.dashboard')],
        default => ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')],
    };
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], $area];
    if (isset($resourceName)) {
        $breadcrumbs[] = ['label' => $resourceName];
    }
    if (request()->routeIs('admin.curricula.edit')) {
        $breadcrumbs[array_key_last($breadcrumbs)]['url'] = route('admin.curricula.show', request()->route('curriculum'));
        $breadcrumbs[] = ['label' => 'Sunting'];
    }
@endphp

@section('title', $title)

@section('content')
    <section class="portal-card dashboard-placeholder">
        <p class="eyebrow">AREA INTERNAL</p>
        <h1>{{ $title }}</h1>
        @isset($resourceName)
            <p>{{ $resourceName }}</p>
        @endisset
        <p>Fitur pengelolaan dan monitoring pada halaman ini akan tersedia pada tahap berikutnya.</p>
        <a href="{{ route('dashboard') }}">Kembali ke dashboard</a>
        @if(request()->route('curriculum') instanceof \App\Models\Curriculum)
            @can('view', request()->route('curriculum'))
                <p><a href="{{ route('internal.curricula.history', request()->route('curriculum')) }}">Riwayat Kurikulum</a></p>
            @endcan
        @endif
    </section>
@endsection
