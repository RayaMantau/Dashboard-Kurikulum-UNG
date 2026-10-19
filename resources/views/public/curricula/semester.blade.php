@extends('layouts.public')
@section('title', 'Semester '.$semester->semester_number.' - '.$curriculum->name)
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Program Studi', 'url' => route('study-programs.index')], ['label' => $studyProgram->name, 'url' => route('study-programs.show', $studyProgram->slug)], ['label' => 'Kurikulum', 'url' => route('study-programs.curricula.index', $studyProgram->slug)], ['label' => $curriculum->name, 'url' => route('study-programs.curricula.show', [$studyProgram->slug, $curriculum->id])], ['label' => 'Semester '.$semester->semester_number]]" />
        <header class="public-page-heading">
            <span class="public-badge {{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'public-badge-archive' : '' }}">{{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif' }}</span>
            <h1>Semester {{ $semester->semester_number }} &mdash; {{ $semester->name }}</h1>
            <p>{{ $curriculum->name }} &middot; {{ $studyProgram->name }}</p>
            <p>Total SKS semester (tercatat): {{ $semester->total_sks }}</p>
        </header>
        <section aria-labelledby="courses-title">
            <div class="section-heading"><h2 id="courses-title">Mata Kuliah</h2></div>
            <x-public.course-list :attachments="$semester->curriculumCourses" />
        </section>
    </div>
@endsection
