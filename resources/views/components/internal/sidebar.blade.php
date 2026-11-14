@props(['scopeLabel'])

<aside id="internal-sidebar" class="shell-sidebar" aria-label="Navigasi aplikasi" tabindex="-1">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}"><img class="ung-logo" src="{{ asset('images/ung-logo.png') }}" alt="Logo Universitas Negeri Gorontalo" width="40" height="40"><span>SIMKUR UNG<small>PORTAL AKADEMIK</small></span></a>
        <button type="button" class="icon-button sidebar-close" data-sidebar-close aria-label="Tutup navigasi">&times;</button>
    </div>
    <div class="sidebar-scope">
        <span class="sidebar-caption">RUANG KERJA</span>
        <p>{{ $scopeLabel }}</p>
    </div>
    <nav aria-label="Menu utama">
        <span class="sidebar-caption">MENU UTAMA</span>
        <ul class="sidebar-menu">
            <li><a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-internal.nav-icon name="dashboard" />Dashboard</a></li>
            @can('access-role', 'university_admin')
                <li><a href="{{ route('university-admin.monitoring.index') }}" @if(request()->routeIs('university-admin.monitoring.*')) aria-current="page" @endif><x-internal.nav-icon name="monitoring" />Monitoring Kurikulum</a></li>
            @endcan
            @can('viewAny', \App\Models\Faculty::class)
                <li><a href="{{ route('university-admin.faculties.index') }}" @if(request()->routeIs('university-admin.faculties.*')) aria-current="page" @endif><x-internal.nav-icon name="faculty" />Fakultas</a></li>
            @endcan
            @can('viewAny', \App\Models\StudyProgram::class)
                <li><a href="{{ route('university-admin.study-programs.index') }}" @if(request()->routeIs('university-admin.study-programs.*')) aria-current="page" @endif><x-internal.nav-icon name="study-program" />Program Studi</a></li>
            @endcan
            @can('viewAny', \App\Models\User::class)
                <li><a href="{{ route('university-admin.users.index') }}" @if(request()->routeIs('university-admin.users.*')) aria-current="page" @endif><x-internal.nav-icon name="users" />Pengguna</a></li>
            @endcan
            @can('access-role', 'faculty_admin')
                <li><a href="{{ route('faculty-admin.monitoring.index') }}" @if(request()->routeIs('faculty-admin.monitoring.*')) aria-current="page" @endif><x-internal.nav-icon name="monitoring" />Monitoring Kurikulum</a></li>
            @endcan
            @can('access-role', 'study_program_admin')
                <li><a href="{{ route('admin.curricula.index') }}" @if(request()->routeIs('admin.curricula.*')) aria-current="page" @endif><x-internal.nav-icon name="curriculum" />Kurikulum</a></li>
            @endcan
            @can('viewAny', \App\Models\Course::class)
                <li><a href="{{ route('admin.courses.index') }}" @if(request()->routeIs('admin.courses.*')) aria-current="page" @endif><x-internal.nav-icon name="courses" />Mata Kuliah</a></li>
            @endcan
            @can('access-role', 'head_of_study_program')
                <li><a href="{{ route('kaprodi.submissions.index') }}" @if(request()->routeIs('kaprodi.*')) aria-current="page" @endif><x-internal.nav-icon name="review" />Review Kurikulum</a></li>
            @endcan
        </ul>
    </nav>
    <div class="sidebar-bottom">
        <p>SIMKUR UNG</p>
    </div>
</aside>
