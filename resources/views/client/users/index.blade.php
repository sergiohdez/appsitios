@extends('client.layout')

@section('title', 'Usuarios del cliente')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN DEL CLIENTE</p><h1>Usuarios</h1>
            <p class="muted">Cada usuario tiene un solo perfil. Las cuentas nuevas reciben un enlace para definir su contraseña.</p></div>
        <a class="button primary" href="{{ route('client.users.create') }}">Crear usuario</a>
    </section>
    <section class="panel">
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Nombre</th><th>Correo</th><th>Perfil</th><th>Sitios de aprobación</th><th>Cuenta</th><th>Membresía</th><th></th></tr></thead>
            <tbody>
                @forelse ($memberships as $membership)
                    <tr>
                        <td>{{ $membership->user->name }}</td>
                        <td>{{ $membership->user->email }}</td>
                        <td>{{ $membership->role->label() }}</td>
                        <td>{{ $membership->canApprove() ? ($membership->approvableSites->pluck('name')->join(', ') ?: 'Sin sitios') : '—' }}</td>
                        <td>{{ $membership->user->email_verified_at ? 'Activada' : 'Pendiente' }}</td>
                        <td><span class="state-chip {{ $membership->is_active ? 'active' : 'inactive' }}">{{ $membership->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td class="row-actions">
                            <a class="button quiet" href="{{ route('client.users.edit', $membership->id) }}">Editar</a>
                            <form method="post" action="{{ route('client.users.invitation', $membership->id) }}">
                                @csrf<button class="button quiet" type="submit">Enviar enlace</button>
                            </form>
                            @if ($membership->pending_approvals_count || $membership->pending_requests_count)
                                <button class="button quiet" type="button" disabled title="Resuelve primero las solicitudes pendientes">Eliminar</button>
                            @else
                                <form method="post" action="{{ route('client.users.destroy', $membership->id) }}" onsubmit="return confirm('¿Eliminar este usuario? Se conservarán las solicitudes y auditorías existentes.')">
                                    @csrf @method('DELETE')<button class="button quiet" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell">Aún no hay usuarios creados.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $memberships->links() }}
    </section>
@endsection
