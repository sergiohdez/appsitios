@extends('client.layout')

@section('title', 'Formulario de solicitudes')

@section('content')
    @php
        $configuredFields = old('form_fields', data_get($formVersion?->schema, 'fields', []));
        $dateFormats = config('request_forms.date_formats');
        $fileExtensions = config('request_forms.file_extensions');
        $builderConfig = [
            'dateFormats' => $dateFormats,
            'fileExtensions' => $fileExtensions,
            'textMaxLength' => config('request_forms.text_max_length'),
            'textareaMaxLines' => config('request_forms.textarea_max_lines'),
            'textareaMaxCharacters' => config('request_forms.textarea_max_characters'),
        ];
    @endphp
    <section class="page-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN DEL CLIENTE</p><h1>Formulario de solicitudes</h1>
            <p class="muted">Los campos adicionales que configures aquí aparecerán en las solicitudes de todos los sitios de tu organización.</p></div>
        <span class="status-pill"><i></i> Compartido por cliente</span>
    </section>
    <section class="panel">
        <form id="request-form-builder" method="post" action="{{ route('client.request-form.update') }}" class="stack">
            @csrf
            @method('PATCH')
            <section class="form-builder" aria-labelledby="form-builder-heading">
                <div class="form-builder-heading">
                    <div>
                        <p class="eyebrow">FORMULARIO BASE + CAMPOS DEL CLIENTE</p>
                        <h2 id="form-builder-heading">Campos adicionales</h2>
                        <p class="muted">Los datos básicos, las fechas, los adjuntos generales y al menos una persona siempre se solicitan. Agrega aquí los campos comunes a todos los sitios.</p>
                    </div>
                    <button class="button quiet" type="button" id="add-form-field">Agregar campo</button>
                </div>
                <div id="form-fields" class="form-field-list">
                    @foreach ($configuredFields as $index => $field)
                        @include('client.request-form-field', compact('field', 'index', 'dateFormats', 'fileExtensions'))
                    @endforeach
                </div>
                <p class="muted form-builder-empty" id="form-fields-empty" @if (count($configuredFields)) hidden @endif>Aún no hay campos adicionales. Puedes guardar el formulario básico.</p>
            </section>
            <div class="form-actions">
                <button class="button primary" type="submit">Publicar formulario</button>
                <a class="button quiet" href="{{ route('client.dashboard') }}">Cancelar</a>
            </div>
            @if ($formVersion)
                <p class="muted">Versión publicada: {{ $formVersion->version }} · {{ $formVersion->published_at?->format('Y-m-d H:i') }}</p>
            @endif
        </form>
    </section>
    <script type="application/json" id="request-form-builder-config">@json($builderConfig)</script>
    <script src="{{ asset('js/request-form-date.js') }}" defer></script>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/request-form-builder.js') }}?v={{ filemtime(public_path('js/request-form-builder.js')) }}" defer></script>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}">
@endpush
