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
            <li>Escanea el código QR para agregar la cuenta. El tipo es basado en tiempo (TOTP), con seis dígitos y período de 30 segundos.</li>
            <li>Escribe el código actual para confirmar que la configuración quedó correcta.</li>
        </ol>
        <div class="mfa-setup-grid">
            <div class="mfa-qr-column">
                <div class="mfa-qr-card">
                    <img class="mfa-qr-code" src="{{ $qrCodeDataUri }}" alt="Código QR para configurar el autenticador TOTP">
                </div>
                <p class="muted mfa-qr-help">El código QR es temporal y vence en 10 minutos.</p>
            </div>
            <details class="mfa-manual">
                <summary>¿No puedes escanearlo? Configuración manual</summary>
                <p class="muted">En el autenticador, agrega una cuenta con la siguiente clave. Guárdala de forma segura; se muestra solo durante esta configuración.</p>
                <label class="secret-label">Clave de configuración
                    <input class="secret-input" type="text" value="{{ $secret }}" readonly autocomplete="off" spellcheck="false">
                </label>
                <details class="uri-details"><summary>Mostrar URI de aprovisionamiento</summary><code>{{ $provisioningUri }}</code></details>
            </details>
        </div>
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
