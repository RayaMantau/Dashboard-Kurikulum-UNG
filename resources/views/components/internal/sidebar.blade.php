@props(['scopeLabel'])

<aside id="internal-sidebar" class="shell-sidebar" aria-label="Navigasi aplikasi" tabindex="-1">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">U</span><span>Kurikulum UNG<small>PORTAL AKADEMIK</small></span></a>
        <button type="button" class="icon-button sidebar-close" data-sidebar-close aria-label="Tutup navigasi">&times;</button>
    </div>
    <div class="sidebar-scope">
        <span class="sidebar-caption">RUANG KERJA</span>
        <p>{{ $scopeLabel }}</p>
    </div>
    <nav aria-label="Menu utama">
        <span class="sidebar-caption">MENU UTAMA</span>
        <ul class="sidebar-menu">
            <li><a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9638;</span>Dashboard</a></li>
            @can('access-role', 'university_admin')
                <li><a href="{{ route('university-admin.dashboard') }}" @if(request()->routeIs('university-admin.dashboard', 'university-admin.curricula.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9671;</span>Administrasi Universitas</a></li>
            @endcan
            @can('viewAny', \App\Models\Faculty::class)
                <li><a href="{{ route('university-admin.faculties.index') }}" @if(request()->routeIs('university-admin.faculties.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9636;</span>Fakultas</a></li>
            @endcan
            @can('viewAny', \App\Models\StudyProgram::class)
                <li><a href="{{ route('university-admin.study-programs.index') }}" @if(request()->routeIs('university-admin.study-programs.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9636;</span>Program Studi</a></li>
            @endcan
            @can('viewAny', \App\Models\User::class)
                <li><a href="{{ route('university-admin.users.index') }}" @if(request()->routeIs('university-admin.users.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9636;</span>Pengguna</a></li>
            @endcan
            @can('access-role', 'faculty_admin')
                <li><a href="{{ route('faculty-admin.dashboard') }}" @if(request()->routeIs('faculty-admin.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9671;</span>Monitoring Fakultas</a></li>
            @endcan
            @can('access-role', 'study_program_admin')
                <li><a href="{{ route('admin.curricula.index') }}" @if(request()->routeIs('admin.curricula.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9636;</span>Kurikulum</a></li>
            @endcan
            @can('viewAny', \App\Models\Course::class)
                <li><a href="{{ route('admin.courses.index') }}" @if(request()->routeIs('admin.courses.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#9636;</span>Mata Kuliah</a></li>
            @endcan
            @can('access-role', 'head_of_study_program')
                <li><a href="{{ route('kaprodi.submissions.index') }}" @if(request()->routeIs('kaprodi.*')) aria-current="page" @endif><span aria-hidden="true" class="nav-symbol">&#10003;</span>Review Kurikulum</a></li>
            @endcan
        </ul>
    </nav>
    <div class="sidebar-bottom">
        <a href="{{ route('home') }}">Kembali ke beranda <span aria-hidden="true">&nearr;</span></a>
        <p>Dashboard Kurikulum UNG</p>
    </div>
</aside>
