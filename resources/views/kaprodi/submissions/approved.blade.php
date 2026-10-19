@extends('layouts.application')
@section('title', 'Publikasi Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Publikasi Kurikulum']];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Publikasi Kurikulum</h1>
        <p>Program Studi {{ $studyProgram->name }}</p>
        <p>Kurikulum yang telah disetujui dan menunggu publikasi.</p>
        <p><a href="{{ route('kaprodi.submissions.index') }}">Kembali ke antrean review</a></p>
    </header>
    <section class="portal-card">
        @if($curricula->isEmpty())
            <p class="dashboard-empty">Tidak ada kurikulum yang menunggu publikasi.</p>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Antrean publikasi kurikulum" tabindex="0">
                <table class="dashboard-table">
                    <thead><tr><th scope="col">Kurikulum</th><th scope="col">Tahun</th><th scope="col">Disetujui</th><th scope="col">Total SKS</th><th scope="col">Jumlah Semester</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($curricula as $curriculum)
                            <tr>
                                <th scope="row">{{ $curriculum->name }}</th>
                                <td>{{ $curriculum->year }}</td>
                                <td>{{ $curriculum->approved_at?->format('d/m/Y H:i') ?? 'Waktu persetujuan belum tercatat' }}</td>
                                <td>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</td>
                                <td>{{ $curriculum->total_semesters }}</td>
                                <td><a href="{{ route('kaprodi.submissions.publish.confirm', $curriculum) }}">Publikasikan</a> <a href="{{ route('internal.curricula.history', $curriculum) }}">Riwayat</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$curricula" />
    </section>
@endsection
