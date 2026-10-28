@extends('client.layout')

@section('title', $editing ? 'Editar usuario' : 'Crear usuario')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN DEL CLIENTE · USUARIOS</p><h1>{{ $editing ? 'Editar usuario' : 'Crear usuario' }}</h1>
            <p class="muted">Los perfiles de usuario son aprobador o solicitante. El administrador del cliente es una cuenta separada.</p></div>
        <a class="button quiet" href="{{ route('client.users.index') }}">Volver</a>
    </section>
    <section class="panel">
        @php($role = old('role', $membership->role?->value ?? 'requester'))
        @php($selectedSiteIds = old('site_ids', $selectedSiteIds ?? []))
        <form method="post" action="{{ $editing ? route('client.users.update', $membership->id) : route('client.users.store') }}" class="stack">
            @csrf
            @if ($editing) @method('PATCH') @endif
            <div class="form-grid">
                <label>Nombre
                    <input name="name" value="{{ old('name', $membership->user?->name) }}" maxlength="255" required>
                </label>
                @if ($editing)
                    <label>Correo de acceso
                        <input type="email" value="{{ $membership->user->email }}" disabled>
                    </label>
                @else
                    <label>Correo de acceso
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                    </label>
                @endif
                <label>Perfil
                    <select name="role" id="membership-role" required>
                        <option value="requester" @selected($role === 'requester')>Solicitante</option>
                        <option value="approver" @selected($role === 'approver')>Aprobador</option>
                    </select>
                </label>
                <div id="approver-sites" class="wide approver-sites" @if ($role !== 'approver') hidden @endif>
                    <p class="field-title">Sitios donde puede aprobar</p>
                    <p class="muted">El aprobador también puede crear solicitudes. No puede aprobar una solicitud que haya creado.</p>
                    @if ($sites->isEmpty())
                        <p class="notice error">Primero crea un sitio para asignarlo a un aprobador.</p>
                    @else
                        <div class="site-checkboxes">
                            @foreach ($sites as $site)
                                <label class="check-field">
                                    <input type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, $selectedSiteIds, true))>
                                    {{ $site->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($editing)
                    <label class="check-field wide">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $membership->is_active))>
                        Membresía activa
                    </label>
                @endif
            </div>
            <div class="form-actions">
                <button id="membership-submit" class="button primary" type="submit" @disabled($role === 'approver' && empty($selectedSiteIds))>{{ $editing ? 'Guardar cambios' : 'Crear usuario y enviar invitación' }}</button>
                <a class="button quiet" href="{{ route('client.users.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/client.js') }}" defer></script>
@endsection
