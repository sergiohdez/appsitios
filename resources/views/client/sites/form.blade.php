@extends('client.layout')

@section('title', $editing ? 'Editar sitio' : 'Crear sitio')

@section('content')
    @php
        $configuredFields = old('form_fields', data_get($site->form_schema, 'fields', []));
    @endphp
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
            <section class="form-builder" aria-labelledby="form-builder-heading">
                <div class="form-builder-heading">
                    <div>
                        <p class="eyebrow">FORMULARIO DE SOLICITUD</p>
                        <h2 id="form-builder-heading">Campos adicionales</h2>
                        <p class="muted">Los datos básicos de la solicitud y al menos una persona siempre se solicitan. Agrega aquí los campos propios de este sitio.</p>
                    </div>
                    <button class="button quiet" type="button" id="add-form-field">Agregar campo</button>
                </div>
                <div id="form-fields" class="form-field-list">
                    @foreach ($configuredFields as $index => $field)
                        <article class="form-field-card" data-form-field>
                            <input type="hidden" name="form_fields[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}">
                            <label>Etiqueta
                                <input name="form_fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" maxlength="120" required>
                            </label>
                            <label>Tipo de campo
                                <select name="form_fields[{{ $index }}][type]" data-field-type required>
                                    @foreach (['text' => 'Texto', 'textarea' => 'Texto largo', 'date' => 'Fecha', 'select' => 'Lista de opciones', 'file' => 'Archivo (PDF, JPG o PNG)'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($field['type'] ?? 'text') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-options" @if (($field['type'] ?? '') !== 'select') hidden @endif>Opciones (una por línea)
                                <textarea name="form_fields[{{ $index }}][options]" rows="3" placeholder="Opción 1&#10;Opción 2">{{ is_array($field['options'] ?? null) ? implode("\n", $field['options']) : ($field['options'] ?? '') }}</textarea>
                            </label>
                            <label class="check-field">
                                <input type="checkbox" name="form_fields[{{ $index }}][required]" value="1" @checked((bool) ($field['required'] ?? false))>
                                Obligatorio
                            </label>
                            <button class="button quiet remove-form-field" type="button">Quitar campo</button>
                        </article>
                    @endforeach
                </div>
                <p class="muted form-builder-empty" id="form-fields-empty" @if (count($configuredFields)) hidden @endif>Aún no hay campos adicionales. Puedes guardar el sitio con el formulario básico.</p>
            </section>
            <div class="form-actions">
                <button class="button primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear sitio' }}</button>
                <a class="button quiet" href="{{ route('client.sites.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/site-form-builder.js') }}" defer></script>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}">
@endpush
