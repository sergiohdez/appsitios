$(() => {
  const $fields = $('#form-fields');
  const config = JSON.parse(document.querySelector('#request-form-builder-config')?.textContent || '{}');
  const dateFormats = config.dateFormats || {};
  const fileExtensions = config.fileExtensions || [];
  let nextIndex = 0;

  function defaultPlaceholder(type, format) {
    if (type === 'date') return dateFormats[format] || format;
    if (type === 'textarea') return 'Escribe el texto que se sugerirá';
    if (type === 'select') return 'Debe coincidir con una opción';
    return 'Escribe el valor que se sugerirá';
  }

  function updateDateFormatDisplay($card, format) {
    const label = dateFormats[format] || format;
    $card.find('[data-default-value]').attr('placeholder', label);
    $card.find('[data-date-format-hint]').text('Formato: ' + label);
  }

  $fields.find('[name^="form_fields["]').each(function () {
    const match = this.name.match(/^form_fields\[(\d+)\]/);
    if (match) nextIndex = Math.max(nextIndex, Number(match[1]) + 1);
  });

  const updateEmptyState = () => $('#form-fields-empty').prop('hidden', $fields.find('[data-form-field]').length > 0);

  function refreshDateDefault($card) {
    const $input = $card.find('[data-date-default]');
    if ($card.find('[data-field-type]').val() !== 'date') {
      if ($card.find('[data-field-type]').val() !== 'select' && $input.length) $input[0].setCustomValidity('');
      $card.find('[data-date-default-error]').prop('hidden', true);
      return;
    }

    const format = $card.find('[data-date-format-select]').val();
    const value = $input.val();
    const dateValidator = window.RequestFormDates;
    const valid = value === '' || Boolean(dateValidator?.isValid(value, format));

    updateDateFormatDisplay($card, format);
    $input.attr('data-date-format', format);
    $input[0].maxLength = dateValidator?.valueLength(format) || 10;
    $input[0].setCustomValidity(valid ? '' : 'La fecha no coincide con el formato o no existe.');
    $card.find('[data-date-default-error]').prop('hidden', valid);
  }

  function refreshSelectSettings($card) {
    const type = $card.find('[data-field-type]').val();
    const $options = $card.find('[data-setting="select"] textarea');
    const $default = $card.find('[data-default-value]');

    if (type !== 'select') {
      if ($options.length) $options[0].setCustomValidity('');
      if (type !== 'date' && $default.length) $default[0].setCustomValidity('');
      return;
    }

    const options = String($options.val() || '')
      .split(/\r\n|[\n\r\v\f\u0085\u2028\u2029]/)
      .map((option) => option.trim())
      .filter((option) => option !== '');
    const defaultValue = String($default.val() || '').trim();

    $options[0].setCustomValidity(options.length ? '' : 'Agrega al menos una opción para este campo de selección.');
    $default[0].setCustomValidity(defaultValue && !options.includes(defaultValue)
      ? 'El valor por defecto debe coincidir con una de las opciones configuradas.'
      : '');
  }

  function syncFieldSettings($card) {
    const type = $card.find('[data-field-type]').val();
    const dateFormat = $card.find('[data-date-format-select]').val();

    $card.find('[data-setting]').each(function () {
      const $setting = $(this);
      const settingType = $setting.data('setting');
      const visible = settingType === type || (settingType === 'default' && type !== 'file');

      $setting.prop('hidden', !visible);
      $setting.find('input,select,textarea').prop('disabled', !visible).prop('required', false);
    });

    const activeDefault = type === 'textarea' ? 'long' : 'short';
    $card.find('[data-default-control]').each(function () {
      const $control = $(this);
      const active = $control.data('default-control') === activeDefault && type !== 'file';
      $control.prop('hidden', !active);
      $control.find('input,textarea').prop('disabled', !active);
    });
    $card.find('[data-date-default-meta]').prop('hidden', type !== 'date');
    const placeholder = defaultPlaceholder(type, dateFormat);
    $card.find('[data-default-value], [data-default-long]').attr('placeholder', placeholder);
    if (type === 'date') updateDateFormatDisplay($card, dateFormat);

    const requiredSetting = type === 'text' ? 'max_length'
      : type === 'textarea' ? 'max_lines'
        : type === 'date' ? 'date_format' : null;
    if (requiredSetting) $card.find('[name$="[' + requiredSetting + ']"]').prop('required', true);

    const $extensions = $card.find('[data-setting="file"] input[type="checkbox"]');
    $extensions.prop('required', false);
    const firstExtension = $extensions.first()[0];
    if (firstExtension) {
      const hasExtension = $extensions.filter(':checked').length > 0;
      firstExtension.setCustomValidity(type === 'file' && !hasExtension ? 'Selecciona al menos una extensión.' : '');
    }

    const shortDefault = $card.find('[data-default-value]')[0];
    if (shortDefault) {
      const maxLength = type === 'text'
        ? Number($card.find('[name$="[max_length]"]').val()) || config.textMaxLength || 2000
        : type === 'date'
          ? window.RequestFormDates?.valueLength($card.find('[data-date-format-select]').val()) || 10
          : config.textareaMaxCharacters || 10000;
      shortDefault.maxLength = maxLength;
    }

    refreshSelectSettings($card);
    refreshDateDefault($card);
  }

  function extensionOptions(index) {
    return fileExtensions.map(function (extension) {
      return '<label class="check-field"><input type="checkbox" name="form_fields[' + index + '][extensions][]" value="' + extension + '" checked>' + extension.toUpperCase() + '</label>';
    }).join('');
  }

  function newFieldMarkup(index) {
    const formatKeys = Object.keys(dateFormats);
    const defaultFormat = formatKeys[0] || 'Y-m-d';
    const formats = Object.entries(dateFormats).map(function (pair) {
      const selected = pair[0] === defaultFormat ? ' selected' : '';
      return '<option value="' + pair[0] + '"' + selected + '>' + pair[1] + '</option>';
    }).join('');
    const inputName = 'form_fields[' + index + ']';
    const maxLength = config.textMaxLength || 2000;
    const maxLines = config.textareaMaxLines || 100;
    const maxCharacters = config.textareaMaxCharacters || 10000;

    return '<article class="form-field-card" data-form-field>'
      + '<input type="hidden" name="' + inputName + '[key]" value="">'
      + '<label>Etiqueta<input name="' + inputName + '[label]" maxlength="120" required></label>'
      + '<label>Tipo de campo<select name="' + inputName + '[type]" data-field-type required>'
      + '<option value="text">Texto</option><option value="textarea">Texto largo</option><option value="date">Fecha</option><option value="select">Lista de opciones</option><option value="file">Archivo</option></select></label>'
      + '<label class="field-setting" data-setting="text">Máximo de caracteres<input type="number" name="' + inputName + '[max_length]" value="' + maxLength + '" min="1" max="' + maxLength + '" data-setting-input required></label>'
      + '<label class="field-setting" data-setting="textarea" hidden>Máximo de líneas<input type="number" name="' + inputName + '[max_lines]" value="' + maxLines + '" min="1" max="' + maxLines + '" data-setting-input disabled></label>'
      + '<label class="field-setting" data-setting="date" hidden>Formato de fecha<select name="' + inputName + '[date_format]" data-date-format-select data-setting-input disabled>' + formats + '</select></label>'
      + '<label class="field-options field-setting" data-setting="select" hidden>Opciones (una por línea)<textarea name="' + inputName + '[options]" rows="3" placeholder="Opción 1&#10;Opción 2" disabled></textarea></label>'
      + '<fieldset class="field-setting extension-settings" data-setting="file" hidden><legend>Extensiones permitidas</legend><div class="extension-options">' + extensionOptions(index) + '</div></fieldset>'
      + '<div class="field-setting default-setting" data-setting="default">'
      + '<label data-default-control="short">Valor por defecto (opcional)<input type="text" name="' + inputName + '[default]" maxlength="' + maxCharacters + '" data-default-value data-date-default></label>'
      + '<label data-default-control="long" hidden>Valor por defecto (opcional)<textarea name="' + inputName + '[default]" rows="3" maxlength="' + maxCharacters + '" data-default-long disabled></textarea></label>'
      + '<div class="date-default-meta" data-date-default-meta hidden><small data-date-format-hint>Formato: ' + (dateFormats[defaultFormat] || defaultFormat) + '</small><small class="date-default-error" data-date-default-error hidden>La fecha no coincide con el formato o no existe.</small></div></div>'
      + '<label class="check-field"><input type="checkbox" name="' + inputName + '[required]" value="1">Obligatorio</label>'
      + '<button class="button quiet remove-form-field" type="button">Quitar campo</button></article>';
  }

  $('#add-form-field').on('click', () => {
    const $card = $(newFieldMarkup(nextIndex++));
    $fields.append($card);
    syncFieldSettings($card);
    updateEmptyState();
  });

  $fields.on('change', '[data-field-type]', function () {
    syncFieldSettings($(this).closest('[data-form-field]'));
  });
  $fields.on('change', '[data-date-format-select]', function () {
    syncFieldSettings($(this).closest('[data-form-field]'));
  });
  $fields.on('change input', '[data-date-default]', function () {
    refreshDateDefault($(this).closest('[data-form-field]'));
  });
  $fields.on('input change', '[data-setting="select"] textarea', function () {
    refreshSelectSettings($(this).closest('[data-form-field]'));
  });
  $fields.on('change', '[data-setting="file"] input[type="checkbox"]', function () {
    syncFieldSettings($(this).closest('[data-form-field]'));
  });
  $fields.on('input', '[data-default-value]', function () {
    const $card = $(this).closest('[data-form-field]');
    $card.find('[data-default-long]').val(this.value);
    refreshSelectSettings($card);
    refreshDateDefault($card);
  });
  $fields.on('input', '[data-default-long]', function () {
    $(this).closest('[data-form-field]').find('[data-default-value]').val(this.value);
  });

  $fields.on('click', '.remove-form-field', function () {
    $(this).closest('[data-form-field]').remove();
    updateEmptyState();
  });

  $fields.find('[data-form-field]').each(function () { syncFieldSettings($(this)); });
  $fields.closest('form').on('submit', function (event) {
    $fields.find('[data-form-field]').each(function () { refreshDateDefault($(this)); });
    if (!this.checkValidity()) event.preventDefault();
  });
  updateEmptyState();
});
