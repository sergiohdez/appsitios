@extends('platform.layout')

@php($editing = $organization->exists)
@section('title', $editing ? 'Editar cliente' : 'Crear cliente')

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">PLATAFORMA · CLIENTES</p><h1>{{ $editing ? 'Editar cliente' : 'Crear cliente' }}</h1>
            <p class="muted">Datos de contacto, facturación y configuración del espacio.</p></div>
        <a class="button quiet" href="{{ $editing ? route('platform.organizations.show', $organization) : route('platform.organizations.index') }}">Volver</a>
    </section>

    <section class="panel">
        @php($brandConfig = old('brand_config', $organization->brand_config ? json_encode($organization->brand_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}'))
        <form method="post" action="{{ $editing ? route('platform.organizations.update', $organization) : route('platform.organizations.store') }}" class="stack">
            @csrf
            @if ($editing) @method('PATCH') @endif
            <div class="form-grid">
                <label>Nombre del cliente
                    <input name="name" value="{{ old('name', $organization->name) }}" maxlength="255" required>
                </label>
                <label>Razón social
                    <input name="legal_name" value="{{ old('legal_name', $organization->legal_name) }}" maxlength="255">
                </label>
                <label>NIT / identificación tributaria
                    <input name="tax_id" value="{{ old('tax_id', $organization->tax_id) }}" maxlength="80" inputmode="numeric" pattern="[0-9]*" data-digits-only>
                </label>
                <label>Correo de facturación
                    <input type="email" name="billing_email" value="{{ old('billing_email', $organization->billing_email) }}" maxlength="255" required>
                </label>
                <label>Persona de contacto
                    <input name="contact_name" value="{{ old('contact_name', $organization->contact_name) }}" maxlength="255">
                </label>
                <label>Correo de contacto
                    <input type="email" name="contact_email" value="{{ old('contact_email', $organization->contact_email) }}" maxlength="255">
                </label>
                <label>Zona horaria
                    <select name="timezone" required>
                        @foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $organization->timezone ?: 'America/Bogota') === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Idioma / locale
                    <select name="locale" required>
                        <option value="es" @selected(old('locale', $organization->locale ?: 'es') === 'es')>Español</option>
                    </select>
                </label>
                <label class="wide">Configuración personalizada (objeto JSON)
                    <textarea name="brand_config" rows="7" spellcheck="false">{{ $brandConfig }}</textarea>
                    <span class="muted">El campo <code>color</code> cambia el color de acento del área del cliente. Admite un nombre CSS (por ejemplo, <code>red</code>) o un color hexadecimal, como {&quot;color&quot;:&quot;#087f83&quot;}.</span>
                </label>
                @unless ($editing)
                    <div class="wide panel-subsection">
                        <p class="eyebrow">ACCESO AL CLIENTE</p>
                        <h2>Administrador inicial</h2>
                        <p class="muted">Se creará una cuenta separada y recibirá un enlace de un solo uso para definir su contraseña.</p>
                    </div>
                    <label>Nombre del administrador inicial
                        <input name="initial_admin_name" value="{{ old('initial_admin_name', $organization->contact_name) }}" maxlength="255" required>
                    </label>
                    <label>Correo de acceso del administrador
                        <input type="email" name="initial_admin_email" value="{{ old('initial_admin_email', $organization->contact_email) }}" maxlength="255" required>
                    </label>
                @endunless
                <label class="check-field wide">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $organization->exists ? $organization->is_active : true))>
                    Cliente activo (si se desactiva, sus miembros no pueden operar)
                </label>
            </div>
            <div class="form-actions">
                <button class="button primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear cliente' }}</button>
                <a class="button quiet" href="{{ $editing ? route('platform.organizations.show', $organization) : route('platform.organizations.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/client.js') }}" defer></script>
@endsection
