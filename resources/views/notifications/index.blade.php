@extends('layouts.application')
@section('title', 'Notifikasi')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Notifikasi']];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Notifikasi</h1>
        <p>Informasi aktivitas kurikulum untuk akun Anda, dari yang terbaru.</p>
        <form method="POST" action="{{ route('notifications.read-all') }}" data-pending-form>
            @csrf
            <button class="primary-button action-button secondary-button" type="submit" data-pending-label="Menandai...">Tandai Semua Sudah Dibaca</button>
        </form>
    </header>
    @forelse($notifications as $notification)
        <article class="portal-card dashboard-section notification-item {{ $notification->read_at ? 'notification-read' : 'notification-unread' }}">
            <h2>{{ $notification->title }}</h2>
            <p><strong>{{ $notification->read_at ? 'Sudah dibaca' : 'Belum dibaca' }}</strong>
                &middot; <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time></p>
            <p class="notification-message">{{ $notification->message }}</p>
            <div class="table-actions">
                @isset($targets[$notification->id])
                    <a class="primary-button action-button" href="{{ $targets[$notification->id] }}">Buka</a>
                @endisset
                @unless($notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" data-pending-form>
                        @csrf
                        <button class="primary-button action-button secondary-button" type="submit" data-pending-label="Menandai...">Tandai Dibaca</button>
                    </form>
                @endunless
            </div>
        </article>
    @empty
        <section class="portal-card"><p class="dashboard-empty">Tidak ada notifikasi.</p></section>
    @endforelse
    <x-internal.pagination :paginator="$notifications" />
@endsection
