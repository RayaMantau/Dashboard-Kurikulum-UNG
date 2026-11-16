<header class="public-header">
    <div class="public-container header-inner">
        <div class="header-top">
            <a class="public-brand" href="{{ route('home') }}" aria-label="SIMKUR UNG, beranda">
                <img class="ung-logo" src="{{ asset('images/ung-logo.png') }}" alt="Logo Universitas Negeri Gorontalo" width="48" height="48">
                <span>SIMKUR UNG<small>Sistem Informasi Monitoring Kurikulum UNG</small></span>
            </a>
            <div class="header-access">
                @auth
                    <a class="public-button button-small" href="{{ route('dashboard') }}">Dashboard Internal</a>
                @else
                    <a class="public-button button-small" href="{{ route('login') }}">Masuk <span aria-hidden="true">&rarr;</span></a>
                @endauth
                <button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-controls="public-drawer" aria-expanded="false">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
        <button class="public-nav-overlay" type="button" aria-label="Tutup menu navigasi" tabindex="-1" hidden></button>
        <div id="public-drawer" aria-label="Menu navigasi publik" tabindex="-1">
            <div class="public-drawer-heading"><strong>SIMKUR UNG</strong><button class="public-menu-close" type="button" aria-label="Tutup menu navigasi"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
            <nav id="public-navigation" aria-label="Navigasi publik">
            <ul>
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a></li>
                <li><a href="{{ route('faculties.index') }}" @if(request()->routeIs('faculties.*')) aria-current="page" @endif>Fakultas</a></li>
                <li><a href="{{ route('study-programs.index') }}" @if(request()->routeIs('study-programs.*')) aria-current="page" @endif>Program Studi</a></li>
                <li><a href="{{ route('search') }}" @if(request()->routeIs('search')) aria-current="page" @endif>Cari Mata Kuliah</a></li>
                <li><a href="{{ route('monitoring.index') }}" @if(request()->routeIs('monitoring.*')) aria-current="page" @endif>Monitoring Kurikulum</a></li>
            </ul>
        </nav>
        </div>
    </div>
</header>
