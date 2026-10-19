@extends('layouts.public')
@section('title', 'Beranda')
@section('content')
    <section class="public-hero" aria-labelledby="hero-title">
        <div class="public-container hero-grid">
            <div class="hero-copy">
                <p class="public-eyebrow"><span aria-hidden="true"></span> PORTAL INFORMASI AKADEMIK</p>
                <h1 id="hero-title">Dashboard Kurikulum <span>Universitas Negeri Gorontalo</span></h1>
                <p class="hero-description">Temukan informasi kurikulum, struktur semester, dan mata kuliah dari Program Studi di Universitas Negeri Gorontalo.</p>
                <div class="hero-actions">
                    <a class="public-button" href="{{ route('faculties.index') }}">Jelajahi Fakultas <span aria-hidden="true">&rarr;</span></a>
                    <span>Terbuka untuk semua.<br>Tanpa perlu akun.</span>
                </div>
                <p class="hero-note"><a href="{{ route('search') }}">Cari mata kuliah dalam kurikulum aktif dan arsip.</a></p>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="art-heading"><span>KURIKULUM UNG</span><span>AKADEMIK</span></div>
                <svg viewBox="0 0 400 270" fill="none">
                    <path d="M52 218L200 262L348 218L200 174Z" fill="var(--color-primary-border)"/>
                    <path d="M72 178L199 218L328 178L200 139Z" fill="var(--color-primary-soft)" stroke="var(--color-primary-border)" stroke-width="2"/>
                    <path d="M80 173V78C124 78 164 92 200 115V213C165 192 124 178 80 173Z" fill="white" stroke="var(--color-primary-border)" stroke-width="2"/>
                    <path d="M320 173V78C276 78 236 92 200 115V213C235 192 276 178 320 173Z" fill="var(--color-primary-border)" stroke="var(--color-primary-border)" stroke-width="2"/>
                    <path d="M102 106C128 108 151 117 177 131M102 125C128 127 151 136 177 150M102 144C121 146 140 152 156 160M224 132C246 119 269 111 295 107M224 151C246 138 269 130 295 126M224 171C240 161 254 155 270 151" stroke="var(--color-primary)" stroke-width="3" stroke-linecap="round"/>
                    <path d="M200 28L244 45L200 62L156 45Z" fill="var(--color-primary)"/>
                    <path d="M173 53V72Q200 90 227 72V53" stroke="var(--color-primary-border)" stroke-width="3"/>
                    <path d="M244 45V78" stroke="var(--color-primary-border)" stroke-width="3"/>
                </svg>
                <div class="art-caption"><strong>Pengetahuan dimulai<br>dari arah yang jelas.</strong><span>Fakultas &middot; Program Studi &middot; Kurikulum</span></div>
            </div>
        </div>
    </section>

    <section id="ringkasan" class="public-container summary-section" aria-labelledby="summary-title">
        <div class="section-heading">
            <div><p class="public-eyebrow">GAMBARAN AKADEMIK</p><h2 id="summary-title">Kurikulum dalam angka</h2></div>
            <p>Ringkasan unit aktif dan kurikulum yang telah dipublikasikan.</p>
        </div>
        <dl class="public-stats">
            @foreach($statistics as $label => $count)
                <div><dt>{{ $label }}</dt><dd>{{ number_format($count, 0, ',', '.') }}</dd></div>
            @endforeach
        </dl>
        <p class="section-note">Program studi dan kurikulum yang ditampilkan berada pada fakultas aktif. Tidak semua program studi memiliki kurikulum yang dipublikasikan.</p>
    </section>

    <section class="public-container public-section" aria-labelledby="faculty-title">
        <div class="section-heading">
            <div><p class="public-eyebrow">LINGKUP KEILMUAN</p><h2 id="faculty-title">Fakultas di UNG</h2></div>
            <p>Cuplikan fakultas aktif di Universitas Negeri Gorontalo.</p>
        </div>
        @if($faculties->isEmpty())
            <p class="public-empty">Belum ada fakultas aktif yang tersedia.</p>
        @else
            <ul class="faculty-grid">
                @foreach($faculties as $faculty)
                    <li class="faculty-card">
                        <span class="faculty-code">{{ $faculty->code }}</span>
                        <h3>{{ $faculty->name }}</h3>
                        <span class="card-caption">Universitas Negeri Gorontalo</span>
                        <a class="public-card-link" href="{{ route('faculties.show', $faculty->slug) }}" aria-label="Lihat Fakultas: {{ $faculty->name }}">Lihat Fakultas <span aria-hidden="true">&rarr;</span></a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="curriculum-section" aria-labelledby="curriculum-title">
        <div class="public-container">
            <div class="section-heading">
                <div><p class="public-eyebrow">INFORMASI TERKINI</p><h2 id="curriculum-title">Kurikulum aktif terbaru</h2></div>
                <p>Cuplikan kurikulum yang telah dipublikasikan oleh program studi.</p>
            </div>
            @if($curricula->isEmpty())
                <p class="public-empty">Belum ada kurikulum aktif yang dipublikasikan.</p>
            @else
                <div class="curriculum-grid">
                    @foreach($curricula as $curriculum)
                        <article class="curriculum-card">
                            <div class="card-top"><span class="public-badge">Dipublikasikan</span><span>Tahun {{ $curriculum->year }}</span></div>
                            <h3>{{ $curriculum->name }}</h3>
                            <p>{{ $curriculum->studyProgram->name }}</p>
                            <p class="card-caption">{{ $curriculum->studyProgram->faculty->name }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
