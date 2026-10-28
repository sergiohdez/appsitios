$(() => {
  const $fields = $('#form-fields');
  let nextIndex = 0;
  $fields.find('[name^="form_fields["]').each(function () {
    const match = this.name.match(/^form_fields\[(\d+)\]/);
    if (match) nextIndex = Math.max(nextIndex, Number(match[1]) + 1);
  });

  const updateEmptyState = () => $('#form-fields-empty').prop('hidden', $fields.find('[data-form-field]').length > 0);

  $('#add-form-field').on('click', () => {
    const index = nextIndex++;
    const markup = `
      <article class="form-field-card" data-form-field>
        <input type="hidden" name="form_fields[${index}][key]" value="">
        <label>Etiqueta<input name="form_fields[${index}][label]" maxlength="120" required></label>
        <label>Tipo de campo
          <select name="form_fields[${index}][type]" data-field-type required>
            <option value="text">Texto</option><option value="textarea">Texto largo</option>
            <option value="date">Fecha</option><option value="select">Lista de opciones</option>
            <option value="file">Archivo (PDF, JPG o PNG)</option>
          </select>
        </label>
        <label class="field-options" hidden>Opciones (una por línea)
          <textarea name="form_fields[${index}][options]" rows="3" placeholder="Opción 1&#10;Opción 2"></textarea>
        </label>
        <label class="check-field"><input type="checkbox" name="form_fields[${index}][required]" value="1">Obligatorio</label>
        <button class="button quiet remove-form-field" type="button">Quitar campo</button>
      </article>`;

    $fields.append(markup);
    updateEmptyState();
  });

  $fields.on('change', '[data-field-type]', function () {
    $(this).closest('[data-form-field]').find('.field-options').prop('hidden', this.value !== 'select');
  });

  $fields.on('click', '.remove-form-field', function () {
    $(this).closest('[data-form-field]').remove();
    updateEmptyState();
  });

  updateEmptyState();
});
