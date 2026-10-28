<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar acceso · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="{{ route('login') }}">Acceso<span>Sitios</span></a>
        <p class="eyebrow">RECUPERACIÓN DE ACCESO</p>
        <h1>Restablece tu contraseña</h1>
        <p class="muted">Escribe el correo asociado a tu cuenta. Si está registrado, te enviaremos un enlace de un solo uso.</p>
        @if (session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('password.email') }}" class="stack">
            @csrf
            <label>Correo electrónico
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus>
            </label>
            <button class="button primary" type="submit">Enviar enlace</button>
        </form>
        <p class="small muted"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
    </main>
</body>
</html>
