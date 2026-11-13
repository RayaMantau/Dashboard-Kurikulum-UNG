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
            </div>
        </div>
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
</header>
