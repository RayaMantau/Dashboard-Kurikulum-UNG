<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') | Dashboard Kurikulum UNG</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <link href="{{ asset('brand.css') }}" rel="stylesheet">
    <link href="{{ asset('internal.css') }}" rel="stylesheet">
    <script src="{{ asset('internal.js') }}" defer></script>
</head>
<body class="internal-portal app-shell">
    <a class="skip-link" href="#main">Lewati ke konten utama</a>
    <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Tutup navigasi" hidden tabindex="-1"></button>
    <x-internal.sidebar :scope-label="$scopeLabel" />
    <div class="shell-workspace" data-shell-workspace>
        <x-internal.topbar :user="$shellUser" :unread-count="$unreadNotificationCount" :title="trim($__env->yieldContent('title', 'Dashboard'))" />
        <main id="main" class="shell-main" tabindex="-1">
            <x-internal.breadcrumbs :items="$breadcrumbs ?? [['label' => 'Dashboard']]" />
            <x-internal.flash-message />
            @yield('content')
        </main>
        <footer class="shell-footer">Universitas Negeri Gorontalo <span aria-hidden="true">&middot;</span> Portal Kurikulum</footer>
    </div>
</body>
</html>
