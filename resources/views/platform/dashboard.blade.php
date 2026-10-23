@extends('platform.layout')

@section('title', 'Administración de plataforma')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">CUENTA DE PLATAFORMA</p><h1>Administración</h1>
            <p class="muted">Gestiona clientes y consulta su operación desde un espacio central.</p></div>
        <span class="status-pill"><i></i> MFA verificado</span>
    </section>
    <section class="platform-stat-grid" aria-label="Resumen de plataforma">
        <article class="panel"><p class="eyebrow">CLIENTES</p><h2>{{ $organizationCount }}</h2><p class="muted">Organizaciones registradas</p></article>
        <article class="panel"><p class="eyebrow">USUARIOS</p><h2>{{ $userCount }}</h2><p class="muted">Cuentas registradas</p></article>
        <article class="panel"><p class="eyebrow">SOLICITUDES</p><h2>{{ $requestCount }}</h2><p class="muted">Solicitudes de acceso</p></article>
    </section>
    <section class="panel platform-actions">
        <div><p class="eyebrow">GESTIÓN DE CLIENTES</p><h2>Organizaciones y actividad</h2>
            <p class="muted">Crea o actualiza clientes, suspende espacios y consulta usuarios, sitios, solicitudes y adjuntos. Las consultas a datos de cada cliente quedan auditadas.</p></div>
        <a class="button primary" href="{{ route('platform.organizations.index') }}">Abrir clientes →</a>
    </section>
@endsection
