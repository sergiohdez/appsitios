@extends('platform.layout')

@section('title', 'Solicitud de acceso')

@section('content')
    @php($formSnapshot = $accessRequest->organizationFormVersion ?? $accessRequest->formVersion)
    <section class="page-heading">
        <div><p class="eyebrow">{{ $organization->name }} · SOLICITUD {{ $accessRequest->id }}</p><h1>{{ $accessRequest->site->name }}</h1>
            <p class="muted">Ingreso solicitado para {{ $accessRequest->requested_from->format('Y-m-d H:i') }} hasta {{ $accessRequest->requested_until->format('Y-m-d H:i') }}</p></div>
        <a class="button quiet" href="{{ route('platform.organizations.show', $organization) }}">Volver al cliente</a>
    </section>

    @if ($formSnapshot?->schema['fields'] ?? [])
        <section class="panel section-panel">
            <div class="panel-heading"><div><p class="eyebrow">FORMULARIO · VERSIÓN {{ $formSnapshot->version }}</p><h2>Información adicional</h2></div></div>
            <dl class="detail-grid">
                @foreach ($formSnapshot->schema['fields'] as $field)
                    @php
                        $value = ($field['type'] ?? null) === 'file'
                            ? $accessRequest->attachments->firstWhere('form_field_key', $field['key'] ?? null)?->original_name
                            : data_get($accessRequest->form_data, $field['key'] ?? '');
                    @endphp
                    <div><dt>{{ $field['label'] ?? 'Campo' }}</dt><dd>{{ is_scalar($value) && (string) $value !== '' ? $value : '—' }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">SOLICITUD</p><h2>{{ ucfirst($accessRequest->status->value) }}</h2></div></div>
        <dl class="detail-grid">
            <div><dt>Solicitante</dt><dd>{{ $accessRequest->requester->name }} · {{ $accessRequest->requester->email }}</dd></div>
            <div><dt>Sitio</dt><dd>{{ $accessRequest->site->name }}</dd></div>
            <div><dt>Creada</dt><dd>{{ $accessRequest->created_at->format('Y-m-d H:i') }}</dd></div>
            <div><dt>Decisión</dt><dd>{{ $accessRequest->decided_at?->format('Y-m-d H:i') ?: 'Pendiente' }}</dd></div>
            <div class="wide"><dt>Motivo</dt><dd>{{ $accessRequest->purpose }}</dd></div>
            @if ($accessRequest->decision_comment)<div class="wide"><dt>Comentario de decisión</dt><dd>{{ $accessRequest->decision_comment }}</dd></div>@endif
        </dl>
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">PERSONAS</p><h2>Visitantes registrados</h2></div></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Nombre</th><th>Documento</th><th>Número</th><th>Empresa</th></tr></thead>
            <tbody>
                @forelse ($accessRequest->people as $person)
                    <tr><td>{{ $person->full_name }}</td><td>{{ $person->document_type ?: '—' }}</td><td>{{ $person->document_number ?: '—' }}</td><td>{{ $person->company ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">No hay personas asociadas.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel section-panel">
        <div class="panel-heading"><div><p class="eyebrow">ARCHIVOS</p><h2>Adjuntos</h2></div></div>
        <ul class="attachment-list">
            @forelse ($accessRequest->attachments as $attachment)
                <li><span>{{ $attachment->original_name }} <small>{{ number_format($attachment->size_bytes / 1024, 1) }} KB</small></span>
                    <a class="button quiet" href="{{ route('platform.organizations.requests.attachments.download', [$organization, $accessRequest, $attachment]) }}">Descargar</a></li>
            @empty
                <li class="muted">Esta solicitud no tiene adjuntos.</li>
            @endforelse
        </ul>
    </section>
@endsection
