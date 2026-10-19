@extends('layouts.application')

@section('title', 'Dokumen Kurikulum')
@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Kurikulum', 'url' => route('admin.curricula.index')], ['label' => $curriculum->name], ['label' => 'Dokumen']];
@endphp

@section('content')
    <header class="management-heading">
        <div>
            <p class="eyebrow">DOKUMEN KURIKULUM</p>
            <h1>Dokumen Kurikulum</h1>
            <p>{{ $curriculum->name }} ({{ $curriculum->year }})</p>
            <p>Program Studi {{ $curriculum->studyProgram->name }}</p>
            <x-internal.status-badge :status="$curriculum->status" />
        </div>
        @can('update', $curriculum)
            <a class="primary-button action-button" href="{{ route('admin.curricula.documents.create', $curriculum) }}">Unggah Dokumen</a>
        @endcan
    </header>
    @error('document')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <section class="portal-card">
        @if($documents->isEmpty())
            <p class="dashboard-empty">Belum ada dokumen pada kurikulum ini.</p>
        @else
            <div class="dashboard-table-wrap" role="region" aria-label="Dokumen kurikulum" tabindex="0">
                <table class="dashboard-table">
                    <thead><tr><th scope="col">Judul</th><th scope="col">Nama File</th><th scope="col">Jenis</th><th scope="col">Ukuran</th><th scope="col">Diunggah Oleh</th><th scope="col">Tanggal Upload</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        @foreach($documents as $document)
                            <tr>
                                <th scope="row">{{ $document->title }}</th>
                                <td>{{ $document->file_name }}</td>
                                <td>{{ in_array($document->file_type, ['pdf', 'application/pdf'], true) ? 'PDF' : $document->file_type }}</td>
                                <td>{{ $document->file_size === null ? '-' : ($document->file_size >= 1048576 ? number_format($document->file_size / 1048576, 2, ',', '.').' MB' : number_format($document->file_size / 1024, 2, ',', '.').' KB') }}</td>
                                <td>{{ $document->uploader?->name ?? '-' }}</td>
                                <td>{{ $document->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('admin.curricula.documents.download', [$curriculum, $document]) }}">Unduh</a>
                                    @can('update', $curriculum)
                                        <form method="POST" action="{{ route('admin.curricula.documents.destroy', [$curriculum, $document]) }}" data-pending-form>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="primary-button action-button danger-button" data-pending-label="Menghapus..." aria-label="Hapus dokumen {{ $document->title }}">Hapus Dokumen</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-internal.pagination :paginator="$documents" />
    </section>
@endsection
