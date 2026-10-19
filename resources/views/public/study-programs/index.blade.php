@extends('layouts.public')
@section('title', 'Program Studi')
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Program Studi']]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">PROGRAM AKADEMIK</p><h1>Program Studi</h1>
            <p>Temukan Program Studi di Universitas Negeri Gorontalo dan lihat informasi kurikulum yang tersedia.</p>
        </header>
        <form class="public-filter" method="GET" action="{{ route('study-programs.index') }}">
            <div><label for="faculty">Fakultas</label>
                <select id="faculty" name="faculty">
                    <option value="">Semua Fakultas</option>
                    @foreach($faculties as $faculty)
                        <option value="{{ $faculty->slug }}" @selected($facultyFilter === $faculty->slug)>{{ $faculty->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="public-button" type="submit">Terapkan filter</button>
        </form>
        @if($studyPrograms->isEmpty())
            <p class="public-empty">{{ $facultyFilter !== '' ? 'Tidak ada Program Studi yang tersedia untuk fakultas ini.' : 'Belum ada Program Studi yang tersedia.' }}</p>
        @else
            <div class="faculty-grid">
                @foreach($studyPrograms as $program)
                    <article class="faculty-card">
                        <span class="faculty-code">{{ $program->code }}</span>
                        <h2 class="public-card-title">{{ $program->name }}</h2>
                        <p class="card-caption">{{ $program->faculty->name }}</p>
                        @if(filled($program->degree))<p class="card-caption">Jenjang: {{ $program->degree }}</p>@endif
                        @if(filled($program->accreditation))<p class="card-caption">Akreditasi: {{ $program->accreditation }}</p>@endif
                        <p class="card-caption">{{ $program->has_current_curriculum ? 'Kurikulum tersedia' : 'Kurikulum belum tersedia' }}</p>
                        <a class="public-card-link" href="{{ route('study-programs.show', $program->slug) }}" aria-label="Lihat Program Studi: {{ $program->name }}">Lihat Program Studi <span aria-hidden="true">&rarr;</span></a>
                    </article>
                @endforeach
            </div>
        @endif
        <x-public.pagination :paginator="$studyPrograms" label="program studi" />
    </div>
@endsection
