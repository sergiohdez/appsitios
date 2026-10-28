<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isRecovery ? 'Restablecer contraseña' : 'Activar cuenta' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="{{ route('login') }}">Acceso<span>Sitios</span></a>
        <p class="eyebrow">{{ $isRecovery ? 'RECUPERACIÓN DE ACCESO' : 'ACTIVACIÓN DE CUENTA' }}</p>
        <h1>{{ $isRecovery ? 'Restablece tu contraseña' : 'Define tu contraseña' }}</h1>
        <p class="muted">El enlace es de un solo uso. Si expiró, solicita otro desde la página de inicio de sesión o a quien administra tu cuenta.</p>
        @if ($errors->any())
            <div class="notice error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form id="password-setup-form" method="post" action="{{ route('password.update') }}" class="stack">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Correo electrónico
                <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
            </label>
            <label>Contraseña (mínimo 12 caracteres)
                <input id="account-password" type="password" name="password" autocomplete="new-password" minlength="12" required>
            </label>
            <label>Confirmar contraseña
                <input id="account-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
            </label>
            <button class="button primary" type="submit">{{ $isRecovery ? 'Guardar nueva contraseña' : 'Activar cuenta' }}</button>
        </form>
    </main>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/client.js') }}" defer></script>
</body>
</html>
