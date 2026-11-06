<section class="panel bulk-import" data-client-bulk-import
         data-entity="{{ $entity }}"
         data-preview-url="{{ route('client.imports.preview', $entity) }}">
    <div class="bulk-import-heading">
        <div>
            <h2>Importación masiva</h2>
            @if ($entity === 'users')
                <p class="muted">Carga Nombre, correo electrónico y perfil. Para aprobadores agrega la columna opcional “Sitios asignados”; indica códigos o nombres exactos separados por punto y coma.</p>
            @else
                <p class="muted">Carga Nombre, código, dirección y zona horaria. Código y dirección pueden quedar vacíos.</p>
            @endif
            <p class="muted">Admite CSV, XLS y XLSX. La primera fila puede contener los encabezados; sin ellos se usa el orden indicado.</p>
        </div>
    </div>

    <form class="bulk-import-upload" enctype="multipart/form-data">
        @csrf
        <label for="bulk-import-file-{{ $entity }}">Archivo</label>
        <input id="bulk-import-file-{{ $entity }}" name="file" type="file" accept=".csv,.xls,.xlsx" required>
        <button class="button primary" type="submit">Cargar y contar registros</button>
    </form>

    <p class="bulk-import-message muted" role="status" aria-live="polite"></p>

    <div class="bulk-import-progress" hidden>
        <div class="bulk-import-counts">
            <span class="bulk-import-batch"></span>
            <strong class="bulk-import-current" aria-live="polite"></strong>
        </div>
        <progress class="bulk-import-bar" max="1" value="0"></progress>
        <p class="bulk-import-summary muted"></p>
        <ul class="bulk-import-results" aria-live="polite"></ul>
        <div class="bulk-import-pager" hidden>
            <button class="button quiet bulk-import-page-prev" type="button">Anterior</button>
            <span class="bulk-import-page-info muted"></span>
            <button class="button quiet bulk-import-page-next" type="button">Siguiente</button>
        </div>
        <div class="bulk-import-actions">
            <button class="button primary bulk-import-start" type="button" hidden>Iniciar importación</button>
            <a class="button quiet bulk-import-errors" href="#" hidden>Descargar registros fallidos</a>
        </div>
    </div>
</section>
<script src="{{ asset('js/client-bulk-import.js') }}?v={{ filemtime(public_path('js/client-bulk-import.js')) }}" defer></script>
