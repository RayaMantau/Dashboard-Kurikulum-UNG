@extends('layouts.public')
@section('title', 'Fakultas')
@section('content')
    <div class="public-container public-directory">
        <x-public.breadcrumbs :items="[['label' => 'Beranda', 'url' => route('home')], ['label' => 'Fakultas']]" />
        <header class="public-page-heading">
            <p class="public-eyebrow">UNIT AKADEMIK</p>
            <h1>Fakultas</h1>
            <p>Jelajahi fakultas di Universitas Negeri Gorontalo dan temukan Program Studi serta informasi kurikulum yang tersedia.</p>
        </header>
        @if($faculties->isEmpty())
            <p class="public-empty">Belum ada fakultas yang tersedia.</p>
        @else
            <div class="faculty-grid">
                @foreach($faculties as $faculty)
                    <article class="faculty-card">
                        <span class="faculty-code">{{ $faculty->code }}</span>
                        <h2 class="public-card-title">{{ $faculty->name }}</h2>
                        @if(filled($faculty->description))
                            <p class="public-description">{{ \Illuminate\Support\Str::limit($faculty->description, 180) }}</p>
                        @endif
                        <p class="card-caption">{{ $faculty->study_programs_count }} Program Studi aktif</p>
                        <a class="public-card-link" href="{{ route('faculties.show', $faculty->slug) }}" aria-label="Lihat Fakultas: {{ $faculty->name }}">Lihat Fakultas <span aria-hidden="true">&rarr;</span></a>
                    </article>
                @endforeach
            </div>
        @endif
        <x-public.pagination :paginator="$faculties" />
    </div>
@endsection
