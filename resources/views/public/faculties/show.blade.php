@extends('layouts.public')
@section('title', $faculty->name)
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Fakultas', 'url' => route('faculties.index')], ['label' => $faculty->name]]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">FAKULTAS DI UNG</p>
            <span class="faculty-code">{{ $faculty->code }}</span>
            <h1>{{ $faculty->name }}</h1>
            @if(filled($faculty->description))
                <p class="public-description preserve-lines">{{ $faculty->description }}</p>
            @endif
            <p>{{ $studyProgramCount }} Program Studi aktif</p>
        </header>
        <section aria-labelledby="programs-title">
            <div class="section-heading"><h2 id="programs-title">Program Studi</h2></div>
            @if($studyPrograms->isEmpty())
                <p class="public-empty">Belum ada Program Studi aktif pada fakultas ini.</p>
            @else
                <div class="faculty-grid">
                    @foreach($studyPrograms as $program)
                        <article class="faculty-card">
                            <span class="faculty-code">{{ $program->code }}</span>
                            <h3>{{ $program->name }}</h3>
                            @if(filled($program->degree))<p class="card-caption">Jenjang: {{ $program->degree }}</p>@endif
                            @if(filled($program->accreditation))<p class="card-caption">Akreditasi: {{ $program->accreditation }}</p>@endif
                            <a class="public-card-link" href="{{ route('study-programs.show', $program->slug) }}" aria-label="Lihat Program Studi: {{ $program->name }}">Lihat Program Studi <span aria-hidden="true">&rarr;</span></a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
