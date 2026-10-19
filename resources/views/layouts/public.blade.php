<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Informasi kurikulum Universitas Negeri Gorontalo. Ringkasan fakultas, program studi, dan kurikulum yang telah dipublikasikan.')">
    <title>@yield('title', 'Beranda') | Dashboard Kurikulum UNG</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <link href="{{ asset('brand.css') }}" rel="stylesheet">
    <link href="{{ asset('public.css') }}" rel="stylesheet">
    <script src="{{ asset('public.js') }}" defer></script>
</head>
<body class="public-portal">
    <a class="public-skip" href="#main">Lewati ke konten utama</a>
    <x-public.header />
    <main id="main" tabindex="-1">
        @yield('content')
    </main>
    <x-public.footer />
</body>
</html>
