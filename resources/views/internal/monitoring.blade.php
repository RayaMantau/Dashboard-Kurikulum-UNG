@extends('layouts.application')
@section('title', 'Monitoring Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Monitoring Kurikulum']];
@endphp
@section('content')
    <header class="dashboard-heading"><h1>Monitoring Kurikulum</h1><p>Kurikulum yang sedang dipublikasikan pada Program Studi aktif. Informasi ini hanya untuk pemantauan.</p></header>
    <section class="portal-card">
        <form method="GET" class="management-filters" action="{{ route($university ? 'university-admin.monitoring.index' : 'faculty-admin.monitoring.index') }}">
            <div class="form-field"><label for="search">Cari Program Studi</label><input id="search" name="search" type="search" maxlength="255" value="{{ $filters['search'] ?? '' }}"></div>
            @if($university)
                <div class="form-field"><label for="faculty_id">Fakultas</label><select id="faculty_id" name="faculty_id"><option value="">Semua Fakultas</option>
                    @foreach($faculties as $faculty)<option value="{{ $faculty->id }}" @selected(($filters['faculty_id'] ?? '') == $faculty->id)>{{ $faculty->name }}</option>@endforeach
                </select></div>
            @endif
            <button type="submit" class="primary-button action-button">Terapkan</button>
        </form>
        @if($programs->isEmpty())<p class="dashboard-empty">Tidak ada Program Studi yang sesuai dengan pencarian atau filter.</p>
        @else
            <div class="monitoring-table-wrap" tabindex="0" role="region" aria-label="Monitoring kurikulum internal">
                <table class="monitoring-table">
                    <caption class="sr-only">Kurikulum aktif dan jadwal Monev Program Studi</caption>
                    <thead><tr>@if($university)<th scope="col">Fakultas</th>@endif<th scope="col">Program Studi</th><th scope="col">Tahun Perbaikan Kurikulum</th><th scope="col">Tanggal Pengesahan</th><th scope="col">Dokumen</th><th scope="col">Link File Kurikulum</th><th scope="col">Monev Kurikulum</th></tr></thead>
                    <tbody>
                        @foreach($programs as $program)
                            @php($current = $program->curricula->first())
                            <tr>
                                @if($university)<td>{{ $program->faculty->name }}</td>@endif
                                <th scope="row">{{ $program->name }}<small>{{ $current?->name ?? 'Belum ada kurikulum yang dipublikasikan.' }}</small></th>
                                <td>{{ $current?->year ?? 'Belum tersedia' }}</td>
                                <td>{{ $current?->ratified_at?->locale('id')->translatedFormat('j F Y') ?? 'Belum ditetapkan' }}</td>
                                <td>@if($current && $current->documents_count > 0)<a class="primary-button action-button secondary-button" href="{{ route('study-programs.curricula.show', [$program->slug, $current->id]) }}#documents-title">Lihat Dokumen ({{ $current->documents_count }})</a>@else Belum tersedia @endif</td>
                                <td>@if($current?->safeExternalDocumentUrl())<a class="primary-button action-button secondary-button" href="{{ $current->safeExternalDocumentUrl() }}" target="_blank" rel="noopener noreferrer">Buka File Kurikulum</a>@else Belum tersedia @endif</td>
                                <td><x-monitoring-status :curriculum="$current" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$programs" />
    </section>
@endsection
