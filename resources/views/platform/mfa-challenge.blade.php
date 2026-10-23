@extends('platform.layout')

@section('title', 'Verificación multifactor')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">VERIFICACIÓN DE INICIO DE SESIÓN</p><h1>Confirma tu identidad</h1>
            <p class="muted">Ingresa un código temporal de tu autenticador o uno de tus códigos de recuperación.</p></div>
    </section>
    <section class="panel mfa-panel">
        <form method="post" action="{{ route('platform.mfa.challenge.verify') }}" class="stack mfa-form">
            @csrf
            <label>Código de autenticación
                <input name="code" inputmode="text" autocomplete="one-time-code" maxlength="40" required autofocus>
            </label>
            <button class="button primary" type="submit">Verificar e ingresar</button>
        </form>
        <p class="muted">Los códigos temporales se aceptan una sola vez. Cada código de recuperación también es de un solo uso.</p>
    </section>
@endsection
