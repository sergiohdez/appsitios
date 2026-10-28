<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/">Acceso<span>Sitios</span></a>
        <div class="topbar-user"><span>{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button quiet" type="submit">Salir</button></form>
        </div>
    </header>
    <main class="page-shell">
        <section class="page-heading">
            <div><p class="eyebrow">OPERACIÓN DEL SITIO</p><h1>Solicitudes de acceso</h1>
                <p class="muted">Crea una solicitud o revisa el estado de las que requieren tu atención.</p></div>
            <span class="status-pill"><i></i> Espacio seguro</span>
        </section>
        <div id="flash" aria-live="polite"></div>
        <section class="dashboard-grid">
            <div class="panel request-panel">
                <div class="panel-heading"><div><p class="eyebrow">NUEVA SOLICITUD</p><h2>Solicitar acceso</h2></div><span class="step-mark">01</span></div>
                <form id="request-form" class="stack">
                    <label>Sitio
                        <select name="site_id" id="site_id" required><option value="">Cargando sitios…</option></select>
                    </label>
                    <input name="form_version_id" id="form_version_id" type="hidden">
                    <label>Motivo de ingreso
                        <textarea name="purpose" rows="3" maxlength="1000" placeholder="Describe la actividad que se realizará" required></textarea>
                    </label>
                    <div class="two-columns">
                        <label>Desde<input name="requested_from" type="datetime-local" required></label>
                        <label>Hasta<input name="requested_until" type="datetime-local" required></label>
                    </div>
                    <div id="people-list" class="people-list">
                        <fieldset class="person-card" data-person>
                            <legend class="field-title">Persona que ingresará</legend>
                            <label>Nombre completo<input name="people[0][full_name]" maxlength="180" autocomplete="off" required></label>
                            <div class="two-columns">
                                <label>Tipo de documento<input name="people[0][document_type]" maxlength="40" placeholder="CC, pasaporte…"></label>
                                <label>Número<input name="people[0][document_number]" maxlength="80"></label>
                            </div>
                            <label>Empresa<input name="people[0][company]" maxlength="180"></label>
                            <button class="button quiet remove-person" type="button" hidden>Quitar persona</button>
                        </fieldset>
                    </div>
                    <button class="button quiet add-person" id="add-person" type="button">Agregar otra persona</button>
                    <div id="custom-fields" class="custom-request-fields" aria-live="polite"></div>
                    <label class="file-picker">Adjuntos (PDF, JPG o PNG; hasta 10 MB por archivo)
                        <input name="attachments" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple>
                    </label>
                    <button class="button primary" type="submit">Enviar para aprobación <span aria-hidden="true">→</span></button>
                </form>
            </div>
            <div class="panel inbox-panel">
                <div class="panel-heading"><div><p class="eyebrow">BANDEJA</p><h2>Actividad reciente</h2></div><button class="button quiet" id="refresh" type="button">Actualizar</button></div>
                <div id="request-list" class="request-list"><div class="loading-state">Cargando solicitudes…</div></div>
            </div>
        </section>
        <footer class="page-footer">Las decisiones quedan registradas para consulta y auditoría.</footer>
    </main>
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
