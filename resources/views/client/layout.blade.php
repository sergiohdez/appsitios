<!doctype html>
<html lang="es">
@php
    $brandColor = data_get(request()->attributes->get('tenant')?->brand_config, 'color');
    $brandColor = is_string($brandColor) && preg_match('/^(?:#[a-fA-F0-9]{3,8}|[a-zA-Z]+)$/D', $brandColor) ? $brandColor : null;
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cliente') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/platform.css') }}">
    @stack('styles')
    @if ($brandColor)
        <style>:root { --teal: {{ $brandColor }}; --teal-dark: {{ $brandColor }}; }</style>
    @endif
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('client.dashboard') }}">Acceso<span>Sitios</span></a>
        <nav class="platform-nav" aria-label="Administración del cliente">
            <a href="{{ route('client.dashboard') }}">Resumen</a>
            <a href="{{ route('client.users.index') }}">Usuarios</a>
            <a href="{{ route('client.sites.index') }}">Sitios</a>
        </nav>
        <div class="topbar-user"><span>{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button quiet" type="submit">Salir</button></form>
        </div>
    </header>
    <main class="page-shell">
        @if (session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif
        @if (session('warning')) <div class="notice error" role="alert">{{ session('warning') }}</div> @endif
        @if ($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        @yield('content')
        <footer class="page-footer">{{ request()->attributes->get('tenant')->name }} · Administración del cliente</footer>
    </main>
</body>
</html>
