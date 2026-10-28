@extends('client.layout')

@section('title', $editing ? 'Editar sitio' : 'Crear sitio')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN DEL CLIENTE · SITIOS</p><h1>{{ $editing ? 'Editar sitio' : 'Crear sitio' }}</h1>
            <p class="muted">El sitio solo estará disponible dentro de tu organización.</p></div>
        <a class="button quiet" href="{{ route('client.sites.index') }}">Volver</a>
    </section>
    <section class="panel">
        <form method="post" action="{{ $editing ? route('client.sites.update', $site->id) : route('client.sites.store') }}" class="stack">
            @csrf
            @if ($editing) @method('PATCH') @endif
            <div class="form-grid">
                <label>Nombre del sitio
                    <input name="name" value="{{ old('name', $site->name) }}" maxlength="180" required>
                </label>
                <label>Código interno
                    <input name="code" value="{{ old('code', $site->code) }}" maxlength="80">
                </label>
                <label class="wide">Dirección
                    <input name="address" value="{{ old('address', $site->address) }}" maxlength="255">
                </label>
                <label>Zona horaria
                    <select name="timezone" required>
                        @foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $site->timezone ?: 'America/Bogota') === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($editing)
                    <label class="check-field">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $site->is_active))>
                        Sitio activo (los sitios inactivos no admiten nuevas solicitudes)
                    </label>
                @endif
            </div>
            <div class="form-actions">
                <button class="button primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear sitio' }}</button>
                <a class="button quiet" href="{{ route('client.sites.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
