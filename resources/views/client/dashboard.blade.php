@extends('client.layout')

@section('title', 'Administración del cliente')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRADOR DEL CLIENTE</p><h1>{{ $organization->name }}</h1>
            <p class="muted">Administra las cuentas y los sitios de tu organización.</p></div>
        <span class="status-pill"><i></i> Espacio privado</span>
    </section>
    <section class="platform-stat-grid">
        <article class="panel"><p class="eyebrow">USUARIOS</p><h2>{{ max(0, $organization->memberships_count - 1) }}</h2><p class="muted">Aprobadores y solicitantes</p></article>
        <article class="panel"><p class="eyebrow">SITIOS</p><h2>{{ $organization->sites_count }}</h2><p class="muted">Lugares de acceso restringido</p></article>
        <article class="panel"><p class="eyebrow">TU PERFIL</p><h2>Administrador</h2><p class="muted">Gestión de usuarios y sitios</p></article>
    </section>
    <section class="panel client-actions">
        <article><p class="eyebrow">ACCESO AL CLIENTE</p><h2>Usuarios</h2>
            <p class="muted">Crea usuarios con un perfil único de aprobador o solicitante y asigna sitios a los aprobadores.</p>
            <a class="button primary" href="{{ route('client.users.index') }}">Gestionar usuarios</a></article>
        <article><p class="eyebrow">OPERACIÓN</p><h2>Sitios</h2>
            <p class="muted">Registra los lugares donde se solicitará permiso de acceso.</p>
            <a class="button primary" href="{{ route('client.sites.index') }}">Gestionar sitios</a></article>
        <article><p class="eyebrow">SOLICITUDES</p><h2>Formulario</h2>
            <p class="muted">Configura los campos adicionales que se usarán en las solicitudes de todos tus sitios.</p>
            <a class="button primary" href="{{ route('client.request-form.edit') }}">Configurar formulario</a></article>
    </section>
@endsection
