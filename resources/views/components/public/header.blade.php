<header class="public-header">
    <div class="public-container header-inner">
        <a class="public-brand" href="{{ route('home') }}" aria-label="Dashboard Kurikulum UNG, beranda">
            <span class="public-mark" aria-hidden="true">U</span>
            <span>Kurikulum UNG<small>Universitas Negeri Gorontalo</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-controls="public-navigation" aria-expanded="false" hidden>
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menu
        </button>
        <nav id="public-navigation" aria-label="Navigasi publik">
            <ul>
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a></li>
                <li><a href="{{ route('faculties.index') }}" @if(request()->routeIs('faculties.*')) aria-current="page" @endif>Fakultas</a></li>
                <li><a href="{{ route('study-programs.index') }}" @if(request()->routeIs('study-programs.*')) aria-current="page" @endif>Program Studi</a></li>
                <li><a href="{{ route('search') }}" @if(request()->routeIs('search')) aria-current="page" @endif>Cari Mata Kuliah</a></li>
                <li>
                    @auth
                        <a class="public-button button-small" href="{{ route('dashboard') }}">Dashboard Internal</a>
                    @else
                        <a class="public-button button-small" href="{{ route('login') }}">Masuk <span aria-hidden="true">&rarr;</span></a>
                    @endauth
                </li>
            </ul>
        </nav>
    </div>
</header>
