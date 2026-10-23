@extends('platform.layout')

@section('title', 'Códigos de recuperación')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">GUÁRDALOS EN UN LUGAR SEGURO</p><h1>Códigos de recuperación</h1>
            <p class="muted">Cada código sirve una sola vez. Esta lista se mostrará ahora y no volverá a estar disponible.</p></div>
    </section>
    <section class="panel mfa-panel">
        <ul class="recovery-code-list">
            @foreach ($codes as $code)
                <li><code>{{ $code }}</code></li>
            @endforeach
        </ul>
        <p class="muted">Guárdalos fuera de este equipo. Al regenerar la lista, todos los códigos anteriores dejan de funcionar.</p>
        <a class="button primary" href="{{ route('platform.dashboard') }}">He guardado los códigos</a>
    </section>
@endsection
