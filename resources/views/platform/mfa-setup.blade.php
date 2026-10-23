@extends('platform.layout')

@section('title', 'Configurar autenticación multifactor')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">PASO DE SEGURIDAD OBLIGATORIO</p><h1>Configura tu autenticador</h1>
            <p class="muted">La cuenta administradora requiere un código temporal además de la contraseña.</p></div>
    </section>
    <section class="panel mfa-panel">
        <ol class="instruction-list">
            <li>Abre una aplicación compatible con TOTP, como Microsoft Authenticator, Google Authenticator, 1Password o FreeOTP.</li>
            <li>Agrega una cuenta usando la clave de configuración que aparece abajo. El tipo es basado en tiempo (TOTP), con seis dígitos y período de 30 segundos.</li>
            <li>Escribe el código actual para confirmar que la configuración quedó correcta.</li>
        </ol>
        <label class="secret-label">Clave de configuración (se muestra una sola vez)
            <input class="secret-input" type="text" value="{{ $secret }}" readonly autocomplete="off" spellcheck="false">
        </label>
        <details class="uri-details"><summary>URI de aprovisionamiento manual</summary><code>{{ $provisioningUri }}</code></details>
        <form method="post" action="{{ route('platform.mfa.setup.store') }}" class="stack mfa-form">
            @csrf
            <label>Código de 6 dígitos
                <input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            </label>
            <button class="button primary" type="submit">Activar autenticación multifactor</button>
        </form>
        <p class="muted">La clave temporal vence en 10 minutos. Si expira, vuelve a cargar esta pantalla para generar una nueva.</p>
    </section>
@endsection
