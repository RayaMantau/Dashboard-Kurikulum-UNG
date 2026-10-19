@extends('layouts.application')
@section('title', 'Pengajuan Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Pengajuan Kurikulum']];
@endphp
@section('content')
    <header class="dashboard-heading">
        <h1>Pengajuan Kurikulum</h1>
        <p>Program Studi {{ $studyProgram->name }}</p>
        <p>Review pengajuan yang menunggu keputusan, dimulai dari yang paling lama.</p>
        <p><a href="{{ route('kaprodi.submissions.approved') }}">Kurikulum disetujui &mdash; menunggu publikasi</a></p>
    </header>
    <section class="portal-card">
        @if($curricula->isEmpty())
            <p class="dashboard-empty">Tidak ada kurikulum yang menunggu review.</p>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Antrean review kurikulum" tabindex="0">
                <table class="dashboard-table">
                    <thead><tr><th scope="col">Kurikulum</th><th scope="col">Tahun</th><th scope="col">Diajukan</th><th scope="col">Total SKS</th><th scope="col">Jumlah Semester</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($curricula as $curriculum)
                            <tr>
                                <th scope="row">{{ $curriculum->name }}</th>
                                <td>{{ $curriculum->year }}</td>
                                <td>{{ $curriculum->submitted_at?->format('d/m/Y H:i') ?? 'Waktu pengajuan belum tercatat' }}</td>
                                <td>{{ number_format((float) $curriculum->total_sks, 2, ',', '.') }}</td>
                                <td>{{ $curriculum->total_semesters }}</td>
                                <td><a href="{{ route('kaprodi.submissions.show', $curriculum) }}">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$curricula" />
    </section>
@endsection
