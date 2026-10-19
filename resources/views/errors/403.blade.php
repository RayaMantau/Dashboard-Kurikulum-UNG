@extends('layouts.internal')

@section('title', 'Akses Ditolak')

@section('content')
    <section class="portal-card dashboard-placeholder">
        <p class="eyebrow">403</p>
        <h1>Akses ditolak</h1>
        <p>Anda tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini.</p>
        <p>Hubungi administrator jika Anda memerlukan bantuan.</p>
        <a href="{{ route('dashboard') }}">Kembali ke dashboard</a>
    </section>
@endsection
