@extends('layouts.public')
@section('title', $curriculum->name)
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Program Studi', 'url' => route('study-programs.index')], ['label' => $studyProgram->name, 'url' => route('study-programs.show', $studyProgram->slug)], ['label' => 'Kurikulum', 'url' => route('study-programs.curricula.index', $studyProgram->slug)], ['label' => $curriculum->name]]" />
        <header class="public-page-heading">
            <span class="public-badge {{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'public-badge-archive' : '' }}">{{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif' }}</span>
            <h1>{{ $curriculum->name }}</h1>
            <p><a href="{{ route('study-programs.show', $studyProgram->slug) }}">{{ $studyProgram->name }}</a></p>
            <p><a href="{{ route('faculties.show', $studyProgram->faculty->slug) }}">{{ $studyProgram->faculty->name }}</a></p>
        </header>
        <section aria-labelledby="metadata-title">
            <div class="section-heading"><h2 id="metadata-title">Informasi Kurikulum</h2></div>
            <dl class="public-metadata">
                <div><dt>Tahun</dt><dd>{{ $curriculum->year }}</dd></div>
                @if($curriculum->effective_year !== null)<div><dt>Tahun berlaku</dt><dd>{{ $curriculum->effective_year }}</dd></div>@endif
                <div><dt>Total SKS</dt><dd>{{ $curriculum->total_sks }}</dd></div>
                <div><dt>Jumlah semester</dt><dd>{{ $curriculum->total_semesters }}</dd></div>
            </dl>
            @if(filled($curriculum->description))
                <h3>Deskripsi</h3><p class="preserve-lines">{{ $curriculum->description }}</p>
            @endif
        </section>
        <section class="public-history" aria-labelledby="structure-title">
            <div class="section-heading"><h2 id="structure-title">Struktur Kurikulum</h2></div>
            @forelse($semesters as $semester)
                <section class="public-semester" aria-labelledby="semester-{{ $semester->id }}">
                    <h3 id="semester-{{ $semester->id }}">Semester {{ $semester->semester_number }} &mdash; {{ $semester->name }}</h3>
                    <p class="card-caption">Total SKS semester (tercatat): {{ $semester->total_sks }}</p>
                    <x-public.course-list :attachments="$semester->curriculumCourses" />
                    <a class="public-card-link" href="{{ route('study-programs.curricula.semesters.show', [$studyProgram->slug, $curriculum->id, $semester->id]) }}" aria-label="Lihat Semester {{ $semester->semester_number }}: {{ $semester->name }}">Lihat Semester <span aria-hidden="true">&rarr;</span></a>
                </section>
            @empty
                <p class="public-empty">Belum ada struktur semester yang tersedia.</p>
            @endforelse
        </section>
        <section class="public-history" aria-labelledby="documents-title">
            <div class="section-heading"><h2 id="documents-title">Dokumen Kurikulum</h2></div>
            @if($documents->isEmpty())
                <p class="public-empty">Belum ada dokumen kurikulum yang tersedia.</p>
            @else
                <div class="curriculum-grid">
                    @foreach($documents as $document)
                        <article class="curriculum-card">
                            <h3>{{ $document->title }}</h3>
                            <p>{{ $document->file_name }}</p>
                            <p>PDF &middot; {{ $document->file_size === null ? 'Ukuran belum tercatat' : ($document->file_size >= 1048576 ? number_format($document->file_size / 1048576, 2, ',', '.').' MB' : number_format($document->file_size / 1024, 2, ',', '.').' KB') }}</p>
                            <a class="public-card-link" href="{{ route('study-programs.curricula.documents.download', [$studyProgram->slug, $curriculum->id, $document->id]) }}" aria-label="Unduh Dokumen: {{ $document->title }}">Unduh Dokumen <span aria-hidden="true">&darr;</span></a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
