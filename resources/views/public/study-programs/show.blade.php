@extends('layouts.public')
@section('title', $studyProgram->name)
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Program Studi', 'url' => route('study-programs.index')], ['label' => $studyProgram->name]]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">PROGRAM STUDI DI UNG</p>
            <span class="faculty-code">{{ $studyProgram->code }}</span><h1>{{ $studyProgram->name }}</h1>
            <p><a href="{{ route('faculties.show', $studyProgram->faculty->slug) }}">{{ $studyProgram->faculty->name }}</a></p>
            @if(filled($studyProgram->degree))<p>Jenjang: {{ $studyProgram->degree }}</p>@endif
            @if(filled($studyProgram->accreditation))<p>Akreditasi: {{ $studyProgram->accreditation }}</p>@endif
            @if($website)<p><a href="{{ $website }}" rel="noopener noreferrer">Website Program Studi</a></p>@endif
            @if(filled($studyProgram->description))<p class="preserve-lines">{{ $studyProgram->description }}</p>@endif
        </header>
        <section aria-labelledby="current-title">
            <div class="section-heading"><h2 id="current-title">Kurikulum Aktif</h2></div>
            @if($curriculum)
                <article class="curriculum-card">
                    <span class="public-badge">Kurikulum Aktif</span><h3>{{ $curriculum->name }}</h3>
                    <p>Tahun: {{ $curriculum->year }}</p>
                    @if($curriculum->effective_year !== null)<p>Tahun berlaku: {{ $curriculum->effective_year }}</p>@endif
                    <p>Total SKS: {{ $curriculum->total_sks }}</p><p>Jumlah semester: {{ $curriculum->total_semesters }}</p>
                </article>
            @else
                <p class="public-empty">Belum ada kurikulum aktif yang dipublikasikan.</p>
            @endif
        </section>
        @if($hasPublicCurricula)
            <a class="public-card-link" href="{{ route('study-programs.curricula.index', $studyProgram->slug) }}">Lihat Semua Kurikulum <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>
@endsection
