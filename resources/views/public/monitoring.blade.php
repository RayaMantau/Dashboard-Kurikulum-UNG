@extends('layouts.public')
@section('title', 'Monitoring Kurikulum')
@section('content')
<div class="public-container public-section">
    <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Monitoring Kurikulum']]" />
    <header class="section-heading">
        <div><p class="public-eyebrow">MONITORING AKADEMIK</p><h1>Monitoring Kurikulum</h1></div>
        <p>Kurikulum aktif per Program Studi. Monev Minor dijadwalkan 2 tahun dan Rekonstruksi 4 tahun setelah tanggal pengesahan resmi.</p>
    </header>
    <p class="section-note">Versi arsip tetap tersedia melalui halaman Program Studi. Jadwal Monev menunjukkan periode yang telah dicapai, bukan bukti pelaksanaan evaluasi.</p>
    @if($programs->isEmpty())
        <p class="public-empty">Belum ada Program Studi yang tersedia.</p>
    @else
        <x-monitoring-table :programs="$programs" />
        <x-public.pagination :paginator="$programs" label="Program Studi" />
    @endif
</div>
@endsection
