@extends('layouts.public')
@section('title', 'Cari Mata Kuliah')
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Cari Mata Kuliah']]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">PENCARIAN KURIKULUM</p>
            <h1>Cari Mata Kuliah</h1>
            <p>Temukan mata kuliah dalam kurikulum aktif dan arsip Universitas Negeri Gorontalo.</p>
        </header>
        <form class="public-filter" method="GET" action="{{ route('search') }}">
            <div><label for="q">Kata kunci</label><input id="q" name="q" type="search" maxlength="255" value="{{ $filters['q'] }}" placeholder="Nama atau kode mata kuliah"></div>
            <div><label for="faculty">Fakultas</label><select id="faculty" name="faculty">
                <option value="">Semua Fakultas</option>
                @foreach($faculties as $faculty)
                    <option value="{{ $faculty->slug }}" @selected($filters['faculty'] === $faculty->slug)>{{ $faculty->name }}</option>
                @endforeach
            </select></div>
            <div><label for="study_program">Program Studi</label><select id="study_program" name="study_program">
                <option value="">Semua Program Studi</option>
                @foreach($studyPrograms as $program)
                    <option value="{{ $program->slug }}" @selected($filters['study_program'] === $program->slug)>{{ $program->name }} — {{ $program->faculty->name }}</option>
                @endforeach
            </select></div>
            <div><label for="year">Tahun Kurikulum</label><select id="year" name="year">
                <option value="">Semua Tahun</option>
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected($filters['year'] === (string) $year)>{{ $year }}</option>
                @endforeach
            </select></div>
            <button class="public-button" type="submit">Cari</button>
            <a class="public-card-link" href="{{ route('search') }}">Reset Filter</a>
        </form>
        @if(! $searched)
            <p class="public-empty">Masukkan nama atau kode mata kuliah untuk memulai pencarian. Anda juga dapat memilih filter.</p>
        @else
            <h2>Hasil pencarian{{ $filters['q'] !== '' ? ' untuk “'.$filters['q'].'”' : '' }}</h2>
            <p>{{ $results->total() }} hasil ditemukan.</p>
            @if($results->isEmpty())
                <p class="public-empty">Tidak ada mata kuliah yang sesuai dengan pencarian.</p>
            @else
                <div class="faculty-grid">
                    @foreach($results as $result)
                        <article class="curriculum-card">
                            <span class="public-badge {{ $result->curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'public-badge-archive' : '' }}">{{ $result->curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif' }}</span>
                            <h3>{{ $result->course_code }} — {{ $result->course_name }}</h3>
                            <p>{{ $result->sks }} SKS · {{ $result->course_type->label() }}</p>
                            <p>{{ $result->curriculum->studyProgram->name }} — {{ $result->curriculum->studyProgram->faculty->name }}</p>
                            <p>{{ $result->curriculum->name }} · Tahun {{ $result->curriculum->year }}</p>
                            <p>Semester {{ $result->semester->semester_number }} — {{ $result->semester->name }}</p>
                            <a class="public-card-link" href="{{ route('study-programs.curricula.semesters.show', [$result->curriculum->studyProgram->slug, $result->curriculum_id, $result->semester_id]) }}" aria-label="Lihat semester untuk {{ $result->course_code }} pada {{ $result->curriculum->name }}">Lihat Semester <span aria-hidden="true">&rarr;</span></a>
                        </article>
                    @endforeach
                </div>
            @endif
            <x-public.pagination :paginator="$results" label="hasil pencarian" />
        @endif
    </div>
@endsection
