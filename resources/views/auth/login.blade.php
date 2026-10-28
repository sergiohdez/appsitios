<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="/">Acceso<span>Sitios</span></a>
        <p class="eyebrow">PORTAL DE ACCESO</p>
        <h1>Inicia sesión</h1>
        <p class="muted">Gestiona solicitudes y aprobaciones de tus sitios.</p>
        @if (session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('login.store') }}" class="stack">
            @csrf
            <label>Correo electrónico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </label>
            <label>Contraseña
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button primary" type="submit">Ingresar</button>
        </form>
        <p class="small"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></p>
        <p class="small muted">El acceso a cada cliente está aislado por membresía.</p>
    </main>
</body>
</html>
