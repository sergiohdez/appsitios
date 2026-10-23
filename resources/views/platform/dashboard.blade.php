<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración de plataforma · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('platform.dashboard') }}">Acceso<span>Claro</span></a>
        <div class="topbar-user"><span>{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button quiet" type="submit">Salir</button></form>
        </div>
    </header>
    <main class="page-shell">
        <section class="page-heading">
            <div><p class="eyebrow">CUENTA DE PLATAFORMA</p><h1>Administración</h1>
                <p class="muted">Resumen general de la información de la plataforma.</p></div>
            <span class="status-pill"><i></i> Acceso de administrador</span>
        </section>
        <section class="dashboard-grid" aria-label="Resumen de plataforma">
            <article class="panel"><p class="eyebrow">CLIENTES</p><h2>{{ $organizationCount }}</h2><p class="muted">Organizaciones registradas</p></article>
            <article class="panel"><p class="eyebrow">USUARIOS</p><h2>{{ $userCount }}</h2><p class="muted">Cuentas registradas</p></article>
            <article class="panel"><p class="eyebrow">SOLICITUDES</p><h2>{{ $requestCount }}</h2><p class="muted">Solicitudes de acceso</p></article>
        </section>
        <section class="panel">
            <h2>Cuenta inicial lista</h2>
            <p class="muted">Esta pantalla confirma el acceso de plataforma y muestra indicadores generales. La gestión detallada de clientes y la autenticación multifactor aún deben completarse antes de operar con datos reales.</p>
        </section>
        <footer class="page-footer">La recuperación de acceso se realiza desde la consola del servidor.</footer>
    </main>
</body>
</html>
