@php
    $type = $field['type'] ?? 'text';
    $default = $field['default'] ?? '';
    $selectedExtensions = is_array($field['extensions'] ?? null) ? $field['extensions'] : $fileExtensions;
    $dateFormat = $field['date_format'] ?? 'Y-m-d';
    $dateFormatLabel = $dateFormats[$dateFormat] ?? $dateFormats['Y-m-d'];
    $defaultPlaceholder = match ($type) {
        'date' => $dateFormatLabel,
        'textarea' => 'Escribe el texto que se sugerirá',
        'select' => 'Debe coincidir con una opción',
        default => 'Escribe el valor que se sugerirá',
    };
@endphp
<article class="form-field-card" data-form-field>
    <input type="hidden" name="form_fields[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}">
    <label>Etiqueta
        <input name="form_fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" maxlength="120" required>
    </label>
    <label>Tipo de campo
        <select name="form_fields[{{ $index }}][type]" data-field-type required>
            @foreach (['text' => 'Texto', 'textarea' => 'Texto largo', 'date' => 'Fecha', 'select' => 'Lista de opciones', 'file' => 'Archivo'] as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field-setting" data-setting="text" @if ($type !== 'text') hidden @endif>Máximo de caracteres
        <input type="number" name="form_fields[{{ $index }}][max_length]" value="{{ $field['max_length'] ?? config('request_forms.text_max_length') }}" min="1" max="{{ config('request_forms.text_max_length') }}" data-setting-input required>
    </label>
    <label class="field-setting" data-setting="textarea" @if ($type !== 'textarea') hidden @endif>Máximo de líneas
        <input type="number" name="form_fields[{{ $index }}][max_lines]" value="{{ $field['max_lines'] ?? config('request_forms.textarea_max_lines') }}" min="1" max="{{ config('request_forms.textarea_max_lines') }}" data-setting-input required>
    </label>
    <label class="field-setting" data-setting="date" @if ($type !== 'date') hidden @endif>Formato de fecha
        <select name="form_fields[{{ $index }}][date_format]" data-date-format-select data-setting-input required>
            @foreach ($dateFormats as $format => $label)
                <option value="{{ $format }}" @selected(($field['date_format'] ?? 'Y-m-d') === $format)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field-options field-setting" data-setting="select" @if ($type !== 'select') hidden @endif>Opciones (una por línea)
        <textarea name="form_fields[{{ $index }}][options]" rows="3" placeholder="Opción 1&#10;Opción 2">{{ is_array($field['options'] ?? null) ? implode("\n", $field['options']) : ($field['options'] ?? '') }}</textarea>
    </label>
    <fieldset class="field-setting extension-settings" data-setting="file" @if ($type !== 'file') hidden @endif>
        <legend>Extensiones permitidas</legend>
        <div class="extension-options">
            @foreach ($fileExtensions as $extension)
                <label class="check-field"><input type="checkbox" name="form_fields[{{ $index }}][extensions][]" value="{{ $extension }}" @checked(in_array($extension, $selectedExtensions, true))>{{ strtoupper($extension) }}</label>
            @endforeach
        </div>
    </fieldset>
    <div class="field-setting default-setting" data-setting="default" @if ($type === 'file') hidden @endif>
        <label data-default-control="short" @if ($type === 'textarea') hidden @endif>Valor por defecto (opcional)
            <input type="text" name="form_fields[{{ $index }}][default]" value="{{ is_string($default) ? $default : '' }}" placeholder="{{ $defaultPlaceholder }}" maxlength="{{ config('request_forms.textarea_max_characters') }}" data-default-value data-date-default @if ($type === 'textarea') disabled @endif>
        </label>
        <label data-default-control="long" @if ($type !== 'textarea') hidden @endif>Valor por defecto (opcional)
            <textarea name="form_fields[{{ $index }}][default]" rows="3" placeholder="{{ $defaultPlaceholder }}" maxlength="{{ config('request_forms.textarea_max_characters') }}" data-default-long @if ($type !== 'textarea') disabled @endif>{{ is_string($default) ? $default : '' }}</textarea>
        </label>
        <div class="date-default-meta" data-date-default-meta @if ($type !== 'date') hidden @endif>
            <small class="date-format-hint" data-date-format-hint>Formato: {{ $dateFormatLabel }}</small>
            <small class="date-default-error" data-date-default-error hidden>La fecha no coincide con el formato o no existe.</small>
        </div>
    </div>
    <label class="check-field">
        <input type="checkbox" name="form_fields[{{ $index }}][required]" value="1" @checked((bool) ($field['required'] ?? false))>
        Obligatorio
    </label>
    <button class="button quiet remove-form-field" type="button">Quitar campo</button>
</article>
