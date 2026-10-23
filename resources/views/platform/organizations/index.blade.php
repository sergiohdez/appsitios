@extends('platform.layout')

@section('title', 'Clientes')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">PLATAFORMA</p><h1>Clientes</h1>
            <p class="muted">Administra los espacios y consulta su actividad.</p></div>
        <a class="button primary" href="{{ route('platform.organizations.create') }}">Crear cliente</a>
    </section>

    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('platform.organizations.index') }}">
            <label class="search-field">Buscar
                <input type="search" name="q" value="{{ $search }}" placeholder="Nombre, NIT o correo de facturación">
            </label>
            <label>Estado
                <select name="status">
                    <option value="all" @selected(request('status', 'all') === 'all')>Todos</option>
                    <option value="active" @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Suspendidos</option>
                </select>
            </label>
            <button class="button quiet" type="submit">Filtrar</button>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Cliente</th><th>Facturación</th><th>Usuarios</th><th>Sitios</th><th>Solicitudes</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($organizations as $organization)
                        <tr>
                            <td><a class="table-link" href="{{ route('platform.organizations.show', $organization) }}">{{ $organization->name }}</a><small>{{ $organization->legal_name ?: 'Sin razón social registrada' }}</small></td>
                            <td>{{ $organization->billing_email }}</td>
                            <td>{{ $organization->memberships_count }}</td>
                            <td>{{ $organization->sites_count }}</td>
                            <td>{{ $organization->access_requests_count }}</td>
                            <td><span class="state-chip {{ $organization->is_active ? 'active' : 'inactive' }}">{{ $organization->is_active ? 'Activo' : 'Suspendido' }}</span></td>
                            <td><a class="button quiet" href="{{ route('platform.organizations.edit', $organization) }}">Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">No hay clientes que coincidan con la búsqueda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $organizations->links() }}
    </section>
@endsection
