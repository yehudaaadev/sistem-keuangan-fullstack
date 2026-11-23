<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#14100d">
    <title>Kedai Kopi — Sistem Keuangan</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
    @include('partials.styles')
</head>
<body x-data="shell()" x-init="init()">

@include('partials.svg')

<div x-show="!ready" class="loading">Memuat…</div>

<!-- ================= LOGIN: struk buka shift ================= -->
@include('partials.login')

<!-- ================= APLIKASI ================= -->
<div x-show="ready && $store.auth.user" x-cloak class="app">
    <div class="scrim" :class="menuOpen && 'open'" @click="menuOpen = false"></div>

    @include('partials.sidebar')

    <div class="main">
        @include('partials.topbar')

        <main class="content">
        <template x-for="p in (page ? [page] : [])" :key="p">
        <div>

        @include('partials.pages.katalog')
        @include('partials.pages.pesanan-pelanggan')
        @include('partials.pages.pesanan-staff')
        @include('partials.pages.opsi-grup')
        @include('partials.pages.pengguna')
        @include('partials.pages.shift')
        @include('partials.pages.dashboard')
        @include('partials.pages.crud')
        @include('partials.pages.pos')
        @include('partials.pages.opname')
        @include('partials.pages.wa-blast')
        @include('partials.pages.pengaturan')
        @include('partials.pages.laporan-penjualan')
        @include('partials.pages.belanja')
        @include('partials.pages.laba-rugi')

        </div>
        </template>
        </main>
    </div>
</div>

<!-- ================= MODALS & TOAST ================= -->
@include('partials.modals')

</body>
</html>
