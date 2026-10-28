@extends('client.layout')

@section('title', 'Sitios del cliente')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN DEL CLIENTE</p><h1>Sitios</h1>
            <p class="muted">Lugares físicos donde se gestionan solicitudes de acceso.</p></div>
        <a class="button primary" href="{{ route('client.sites.create') }}">Crear sitio</a>
    </section>
    <section class="panel">
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Nombre</th><th>Código</th><th>Dirección</th><th>Zona horaria</th><th>Solicitudes</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                @forelse ($sites as $site)
                    <tr>
                        <td>{{ $site->name }}</td><td>{{ $site->code ?: '—' }}</td><td>{{ $site->address ?: '—' }}</td><td>{{ $site->timezone }}</td>
                        <td>{{ $site->access_requests_count }}</td><td>{{ $site->is_active ? 'Activo' : 'Inactivo' }}</td>
                        <td class="row-actions">
                            <a class="button quiet" href="{{ route('client.sites.edit', $site->id) }}">Editar</a>
                            @if ($site->approvers_count || $site->access_requests_count)
                                <button class="button quiet" type="button" disabled title="No se puede eliminar un sitio con aprobadores o solicitudes">Eliminar</button>
                            @else
                                <form method="post" action="{{ route('client.sites.destroy', $site->id) }}" onsubmit="return confirm('¿Eliminar este sitio?')">
                                    @csrf @method('DELETE')<button class="button quiet" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell">Aún no hay sitios creados.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $sites->links() }}
    </section>
@endsection
