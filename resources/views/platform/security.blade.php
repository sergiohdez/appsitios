@extends('platform.layout')

@section('title', 'Seguridad de la cuenta')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">CUENTA ADMINISTRADORA</p><h1>Seguridad</h1>
            <p class="muted">El segundo factor se solicita al iniciar sesión y se vuelve a pedir cada 12 horas.</p></div>
        <span class="state-chip {{ $account->mfa_enabled_at ? 'active' : 'inactive' }}">{{ $account->mfa_enabled_at ? 'Autenticador activo' : 'Autenticador pendiente' }}</span>
    </section>

    <section class="panel section-panel">
        <h2>Códigos de recuperación</h2>
        <p class="muted">Quedan {{ $unusedRecoveryCodes }} códigos de un solo uso. Para reemplazarlos, confirma tu contraseña y un código TOTP reciente.</p>
        <form method="post" action="{{ route('platform.security.recovery-codes') }}" class="stack security-form">
            @csrf
            <label>Contraseña actual<input type="password" name="password" autocomplete="current-password" required></label>
            <label>Código TOTP nuevo (6 dígitos)<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
            <button class="button primary" type="submit">Generar nuevos códigos</button>
        </form>
    </section>

    <section class="panel section-panel danger-panel">
        <h2>Restablecer autenticador</h2>
        <p class="muted">Se revocarán todos los códigos de recuperación, sesiones y tokens. La próxima vez que ingreses tendrás que registrar un autenticador nuevo.</p>
        <form method="post" action="{{ route('platform.security.reset-mfa') }}" class="stack security-form">
            @csrf
            <label>Contraseña actual<input type="password" name="password" autocomplete="current-password" required></label>
            <label>Código TOTP nuevo (6 dígitos)<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
            <button class="button quiet danger-button" type="submit">Restablecer y cerrar sesiones</button>
        </form>
    </section>
@endsection
