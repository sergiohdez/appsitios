<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administración') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/platform.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('platform.dashboard') }}">Acceso<span>Sitios</span></a>
        <nav class="platform-nav" aria-label="Administración">
            <a href="{{ route('platform.dashboard') }}">Resumen</a>
            <a href="{{ route('platform.organizations.index') }}">Clientes</a>
            <a href="{{ route('platform.security') }}">Seguridad</a>
        </nav>
        <div class="topbar-user"><span>{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button quiet" type="submit">Salir</button></form>
        </div>
    </header>
    <main class="page-shell">
        @if (session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        @yield('content')
        <footer class="page-footer">AccesoSitios · Administración protegida y auditada</footer>
    </main>
</body>
</html>
