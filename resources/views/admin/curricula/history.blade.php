@extends('layouts.application')
@section('title', 'Riwayat Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Riwayat Kurikulum']];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Riwayat Kurikulum</h1>
        <h2>{{ $curriculum->name }}</h2>
        <p>Program Studi {{ $curriculum->studyProgram->name }} &middot; Tahun {{ $curriculum->year }}</p>
        <x-internal.status-badge :status="$curriculum->status" />
        <p>Dibuat: {{ $curriculum->created_at?->copy()->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Waktu belum tercatat' }}</p>
        <p>Riwayat versi ini bersifat hanya baca. Informasi status di atas tidak menambahkan peristiwa ke riwayat.</p>
    </header>
    <section class="portal-card dashboard-section">
        <h2>Riwayat Workflow</h2>
        <p>Peristiwa yang tercatat, dari yang paling lama. Nama dan peran pelaku mengikuti data pengguna saat ini.</p>
        <ol class="dashboard-records">
            @forelse($events as $event)
                <li>
                    <h3>{{ $event['title'] }}</h3>
                    <p>{{ $event['occurred_at'] ?? 'Waktu belum tercatat' }}</p>
                    <p>{{ $event['actor_name'] }}@if($event['actor_role']) &middot; {{ $event['actor_role'] }}@endif</p>
                </li>
            @empty
                <li class="dashboard-empty">Belum ada riwayat aktivitas untuk kurikulum ini.</li>
            @endforelse
        </ol>
    </section>
    <section class="portal-card dashboard-section">
        <h2>Riwayat Review</h2>
        <p>Catatan review ditampilkan terpisah; setiap catatan merupakan rekaman review tersendiri.</p>
        <ol class="dashboard-records">
            @forelse($reviews as $review)
                <li>
                    <h3>{{ $review['title'] }}</h3>
                    <p>{{ $review['occurred_at'] ?? 'Waktu review belum tercatat' }}</p>
                    <p>{{ $review['actor_name'] }}@if($review['actor_role']) &middot; {{ $review['actor_role'] }}@endif</p>
                    @if(filled($review['notes']))<p>Catatan: {{ $review['notes'] }}</p>@endif
                </li>
            @empty
                <li class="dashboard-empty">Belum ada riwayat review untuk kurikulum ini.</li>
            @endforelse
        </ol>
    </section>
    <p><a href="{{ route('dashboard') }}">Kembali ke dashboard</a></p>
@endsection
