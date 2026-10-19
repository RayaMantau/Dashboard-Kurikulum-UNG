<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Dashboard Kurikulum UNG</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <link href="{{ asset('brand.css') }}" rel="stylesheet">
    <link href="{{ asset('internal.css') }}" rel="stylesheet">
    <script src="{{ asset('internal.js') }}" defer></script>
</head>
<body class="internal-portal">
    <a class="skip-link" href="#main">Lewati ke konten utama</a>
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('home') }}">Dashboard Kurikulum <span>UNG</span></a>
        <a href="{{ route('home') }}">Beranda</a>
    </header>
    <main id="main" class="portal-main" tabindex="-1">
        @yield('content')
    </main>
    <footer class="portal-footer">Universitas Negeri Gorontalo · Portal Kurikulum</footer>
</body>
</html>
