@extends('platform.layout')

@section('title', $organization->name)

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">CLIENTE · {{ $organization->id }}</p><h1>{{ $organization->name }}</h1>
            <p class="muted">{{ $organization->legal_name ?: 'Sin razón social' }} · {{ $organization->billing_email }}</p></div>
        <div class="form-actions">
            <span class="state-chip {{ $organization->is_active ? 'active' : 'inactive' }}">{{ $organization->is_active ? 'Activo' : 'Suspendido' }}</span>
            <a class="button primary" href="{{ route('platform.organizations.edit', $organization) }}">Editar cliente</a>
        </div>
    </section>

    <section class="platform-stat-grid">
        <article class="panel"><p class="eyebrow">USUARIOS</p><h2>{{ $organization->memberships_count }}</h2><p class="muted">Membresías del cliente</p></article>
        <article class="panel"><p class="eyebrow">SITIOS</p><h2>{{ $organization->sites_count }}</h2><p class="muted">Lugares registrados</p></article>
        <article class="panel"><p class="eyebrow">SOLICITUDES</p><h2>{{ $organization->access_requests_count }}</h2><p class="muted">Solicitudes de acceso</p></article>
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">DATOS DEL CLIENTE</p><h2>Contacto y facturación</h2></div></div>
        <dl class="detail-grid">
            <div><dt>Identificación tributaria</dt><dd>{{ $organization->tax_id ?: '—' }}</dd></div>
            <div><dt>Correo de contacto</dt><dd>{{ $organization->contact_email ?: '—' }}</dd></div>
            <div><dt>Persona de contacto</dt><dd>{{ $organization->contact_name ?: '—' }}</dd></div>
            <div><dt>Zona horaria</dt><dd>{{ $organization->timezone }}</dd></div>
            <div><dt>Idioma</dt><dd>{{ $organization->locale }}</dd></div>
            <div class="wide"><dt>Configuración personalizada</dt><dd><pre class="json-preview">{{ json_encode($organization->brand_config ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></dd></div>
        </dl>
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">ACCESO AL CLIENTE</p><h2>Usuarios</h2></div></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Nombre</th><th>Correo</th><th>Funciones</th><th>Estado de cuenta</th><th>Membresía</th></tr></thead>
            <tbody>
                @forelse ($memberships as $membership)
                    <tr>
                        <td>{{ $membership->user->name }}</td>
                        <td>{{ $membership->user->email }}</td>
                        <td class="role-list">
                            @if ($membership->can_manage)<span>Gestor</span>@endif
                            @if ($membership->can_approve)<span>Aprobador</span>@endif
                            @if ($membership->can_request)<span>Solicitante</span>@endif
                        </td>
                        <td><span class="state-chip {{ $membership->user->is_active ? 'active' : 'inactive' }}">{{ $membership->user->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td>{{ $membership->is_active ? 'Activa' : 'Inactiva' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">Este cliente aún no tiene usuarios.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $memberships->links() }}
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">OPERACIÓN</p><h2>Sitios</h2></div></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Nombre</th><th>Código</th><th>Dirección</th><th>Solicitudes</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($sites as $site)
                    <tr><td>{{ $site->name }}</td><td>{{ $site->code ?: '—' }}</td><td>{{ $site->address ?: '—' }}</td><td>{{ $site->access_requests_count }}</td><td>{{ $site->is_active ? 'Activo' : 'Inactivo' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">Este cliente aún no tiene sitios.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $sites->links() }}
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">REVISIÓN GLOBAL</p><h2>Solicitudes recientes</h2></div></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Sitio</th><th>Solicitante</th><th>Personas</th><th>Ingreso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($requests as $accessRequest)
                    <tr>
                        <td>{{ $accessRequest->site->name }}</td>
                        <td>{{ $accessRequest->requester->name }}<small>{{ $accessRequest->requester->email }}</small></td>
                        <td>{{ $accessRequest->people->count() }}</td>
                        <td>{{ $accessRequest->requested_from->format('Y-m-d H:i') }}</td>
                        <td>{{ ucfirst($accessRequest->status->value) }}</td>
                        <td><a class="button quiet" href="{{ route('platform.organizations.requests.show', [$organization, $accessRequest]) }}">Consultar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">Este cliente aún no tiene solicitudes.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $requests->links() }}
    </section>
@endsection
