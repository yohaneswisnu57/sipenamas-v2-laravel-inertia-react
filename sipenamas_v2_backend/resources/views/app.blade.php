<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Sistem Informasi Penelitian dan Pengabdian kepada Masyarakat — Universitas Katolik Widya Mandala Surabaya">
        <title inertia>SIPENAMAS V2 — LPPM UKWMS</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        {{-- Prefix deploy (mis. /refactor/inertia) untuk url() di resources/js/lib/router.jsx. --}}
        <script>window.__SIPENAMAS_BASE__ = @json(request()->getBaseUrl());</script>
        @viteReactRefresh
        @vite('resources/js/app.jsx')
        @inertiaHead
    </head>
    <body class="bg-[#f6f7fb] text-slate-800 antialiased selection:bg-[#188ae2] selection:text-white min-h-screen">
        @inertia
    </body>
</html>
