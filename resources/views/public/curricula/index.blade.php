@extends('layouts.public')
@section('title', 'Kurikulum '.$studyProgram->name)
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Program Studi', 'url' => route('study-programs.index')], ['label' => $studyProgram->name, 'url' => route('study-programs.show', $studyProgram->slug)], ['label' => 'Kurikulum']]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">VERSI KURIKULUM</p><h1>Kurikulum {{ $studyProgram->name }}</h1>
            <p><a href="{{ route('faculties.show', $studyProgram->faculty->slug) }}">{{ $studyProgram->faculty->name }}</a></p>
            <p>Kurikulum aktif dan versi sebelumnya yang tersedia untuk publik.</p>
        </header>
        @if(!$current && $archives->total() === 0)
            <p class="public-empty">Belum ada kurikulum publik yang tersedia.</p>
        @endif
        <section aria-labelledby="current-title">
            <div class="section-heading"><h2 id="current-title">Kurikulum Aktif</h2></div>
            @if($current)
                <x-public.curriculum-card :curriculum="$current" :study-program="$studyProgram" />
            @else
                <p class="public-empty">Belum ada kurikulum aktif yang dipublikasikan.</p>
            @endif
        </section>
        <section class="public-history" aria-labelledby="history-title">
            <div class="section-heading"><h2 id="history-title">Riwayat Kurikulum</h2></div>
            @if($archives->isEmpty())
                <p class="public-empty">Belum ada riwayat kurikulum pada halaman ini.</p>
            @else
                <div class="curriculum-grid">
                    @foreach($archives as $archive)
                        <x-public.curriculum-card :curriculum="$archive" :study-program="$studyProgram" />
                    @endforeach
                </div>
            @endif
            <x-public.pagination :paginator="$archives" label="arsip kurikulum" />
        </section>
    </div>
@endsection
