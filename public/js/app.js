const labels = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Rechazada',
  cancelled: 'Cancelada',
};
const documentTypes = [
  ['CC', 'CC — Cédula de Ciudadanía'],
  ['CE', 'CE — Cédula de Extranjería'],
  ['PP', 'PP — Pasaporte'],
  ['NIT', 'NIT — Número de Identificación Tributaria'],
];

const siteForms = new Map();
let nextPersonIndex = 1;

function csrfToken() {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));

  return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : '';
}

$.ajaxSetup({
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  beforeSend(xhr, settings) {
    if (settings.type !== 'GET' && settings.type !== 'HEAD' && csrfToken()) {
      xhr.setRequestHeader('X-XSRF-TOKEN', csrfToken());
    }
  },
});

function api(path, options = {}) {
  return $.ajax({ url: path, ...options });
}

function errorMessage(error) {
  if (error.status === 401) {
    window.location.assign('/login');

    return 'Tu sesión terminó. Ingresa de nuevo.';
  }

  const payload = error.responseJSON || {};
  const firstValidationError = Object.values(payload.errors || {}).flat()[0];

  return firstValidationError || payload.message || 'No fue posible completar la operación.';
}

function showMessage(message, type = 'success') {
  const $flash = $('#flash');

  $('<div>', { class: `notice ${type}`, role: 'status' })
    .text(message)
    .appendTo($flash.empty());

  window.setTimeout(() => $flash.empty(), 5500);
}

function formatDate(value) {
  return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function validateCustomDate(input) {
  const format = input.dataset.dateFormat || 'Y-m-d';
  const valid = input.value === '' || window.RequestFormDates?.isValid(input.value, format);

  input.setCustomValidity(valid ? '' : `La fecha debe ser válida y usar el formato ${format}.`);
}

function countLines(value) {
  return value === '' ? 0 : value.split(/\r\n|[\n\r\v\f\u0085\u2028\u2029]/).length;
}

function validateCustomTextarea(input) {
  const maxLines = Number(input.dataset.maxLines) || 100;
  const valid = countLines(input.value) <= maxLines;

  input.setCustomValidity(valid ? '' : `El campo admite como máximo ${maxLines} líneas.`);
}

function validateRequestDateRange() {
  const fromInput = document.querySelector('[name="requested_from"]');
  const untilInput = document.querySelector('[name="requested_until"]');
  if (!fromInput || !untilInput) return;

  const from = fromInput.value ? new Date(fromInput.value) : null;
  const until = untilInput.value ? new Date(untilInput.value) : null;
  const now = Date.now();
  let fromMessage = '';
  let untilMessage = '';

  if (fromInput.value && (!from || !Number.isFinite(from.getTime()))) {
    fromMessage = 'Ingresa una fecha y hora de inicio válida.';
  } else if (from && from.getTime() <= now) {
    fromMessage = 'La fecha y hora de inicio debe ser posterior al momento actual.';
  }

  if (untilInput.value && (!until || !Number.isFinite(until.getTime()))) {
    untilMessage = 'Ingresa una fecha y hora de finalización válida.';
  } else if (until && until.getTime() <= now) {
    untilMessage = 'La fecha y hora de finalización debe ser posterior al momento actual.';
  } else if (from && until && until.getTime() <= from.getTime()) {
    untilMessage = 'La fecha y hora de finalización debe ser posterior a la de inicio.';
  }

  fromInput.setCustomValidity(fromMessage);
  untilInput.setCustomValidity(untilMessage);
}

async function loadSites() {
  const $select = $('#site_id');

  try {
    const result = await api('/api/v1/sites');
    const sites = result.data || [];
    siteForms.clear();

    $select.empty().append($('<option>', {
      value: '',
      text: sites.length ? 'Selecciona un sitio' : 'No tienes sitios activos',
    }));

    sites.forEach((site) => {
      siteForms.set(site.id, site);
      $select.append($('<option>', { value: site.id, text: site.name }));
    });
    $select.trigger('change');
  } catch (error) {
    showMessage(errorMessage(error), 'error');
  }
}

function renderCustomFields(site) {
  const $container = $('#custom-fields').empty();
  const fields = site?.form_schema?.fields || [];

  $('#organization_form_version_id').val(site?.organization_form_version_id || '');

  if (!fields.length) {
    return;
  }

  $('<div>', { class: 'custom-fields-heading' })
    .append($('<p>', { class: 'eyebrow' }).text('CAMPOS DEL CLIENTE'))
    .append($('<h3>').text('Información adicional'))
    .appendTo($container);

  fields.forEach((field) => {
    const required = Boolean(field.required);
    const $label = $('<label>').text(`${field.label}${required ? ' · Obligatorio' : ''}`);
    const name = `form_data[${field.key}]`;
    let $input;

    if (field.type === 'select') {
      $input = $('<select>', { name, required }).append($('<option>', { value: '', text: 'Selecciona una opción' }));
      (field.options || []).forEach((option) => {
        $input.append($('<option>', { value: option, text: option, selected: option === field.default }));
      });
    } else if (field.type === 'textarea') {
      const maxLines = Number(field.max_lines) || 100;
      $input = $('<textarea>', { name, rows: Math.min(6, maxLines), maxlength: 10000, required })
        .attr('data-max-lines', maxLines)
        .val(field.default || '');
      validateCustomTextarea($input[0]);
    } else if (field.type === 'file') {
      const extensions = Array.isArray(field.extensions) && field.extensions.length ? field.extensions : ['pdf', 'jpg', 'jpeg', 'png'];
      $input = $('<input>', { name, type: 'file', accept: extensions.map((extension) => `.${extension}`).join(','), required });
    } else if (field.type === 'date') {
      const dateFormat = field.date_format || 'Y-m-d';
      const dateFormatLabel = field.date_format_label || dateFormat;
      $label.append($('<small>', { class: 'muted' }).text(`Formato requerido: ${dateFormatLabel}`));
      $input = $('<input>', { name, type: 'text', inputmode: 'numeric', maxlength: window.RequestFormDates?.valueLength(dateFormat) || 10, placeholder: dateFormatLabel, required })
        .attr('data-request-date', '')
        .attr('data-date-format', dateFormat)
        .val(field.default || '');
      validateCustomDate($input[0]);
    } else {
      const maxLength = Number(field.max_length) || 2000;
      $input = $('<input>', { name, type: 'text', maxlength: maxLength, required, value: field.default || '' });
    }

    $label.append($input).appendTo($container);
  });
}

function updatePersonRemoveButtons() {
  const count = $('#people-list [data-person]').length;
  $('#people-list .remove-person').prop('hidden', count <= 1);
}

function addPerson() {
  const index = nextPersonIndex++;
  const documentOptions = ['<option value="">Selecciona un tipo</option>']
    .concat(documentTypes.map(([value, label]) => `<option value="${value}">${label}</option>`))
    .join('');
  const markup = `
    <fieldset class="person-card" data-person>
      <legend class="field-title">Persona que ingresará</legend>
      <label>Nombre completo<input name="people[${index}][full_name]" maxlength="180" autocomplete="off" required></label>
      <div class="two-columns">
        <label>Tipo de documento<select name="people[${index}][document_type]">${documentOptions}</select></label>
        <label>Número<input name="people[${index}][document_number]" maxlength="80"></label>
      </div>
      <label>Empresa<input name="people[${index}][company]" maxlength="180"></label>
      <button class="button quiet remove-person" type="button">Quitar persona</button>
    </fieldset>`;

  $('#people-list').append(markup);
  updatePersonRemoveButtons();
}

async function loadRequests() {
  const $list = $('#request-list');

  $list.html('<div class="loading-state">Cargando solicitudes…</div>');

  try {
    const result = await api('/api/v1/access-requests');
    const requests = result.data?.data || [];

    if (!requests.length) {
      $list.html('<div class="empty-state">Aún no hay solicitudes para mostrar.</div>');

      return;
    }

    $list.empty();

    requests.forEach((item) => {
      const $row = $('<article>', { class: 'request-row' }).attr('data-request-id', item.id);
      const $info = $('<div>');
      const $title = $('<p>', { class: 'request-title' }).text(item.site?.name || 'Sitio');
      const $meta = $('<p>', { class: 'request-meta' }).text(`${formatDate(item.requested_from)} · ${item.people?.length || 0} persona(s)`);
      const $purpose = $('<p>', { class: 'request-purpose' }).text(item.purpose);
      const $badge = $('<span>', { class: `badge ${item.status}` }).text(labels[item.status] || item.status);

      $info.append($title, $meta);
      $row.append($info, $badge, $purpose);

      const formSchema = item.organization_form_version?.schema || item.form_version?.schema;
      (formSchema?.fields || []).forEach((field) => {
        const attachment = (item.attachments || []).find((itemAttachment) => itemAttachment.form_field_key === field.key);
        const value = field.type === 'file' ? attachment?.original_name : item.form_data?.[field.key];

        if (value !== undefined && value !== null && value !== '') {
          $row.append($('<p>', { class: 'request-purpose custom-request-value' }).text(`${field.label}: ${value}`));
        }
      });

      const $detailsActions = $('<div>', { class: 'request-actions' });
      $('<button>', { type: 'button', class: 'button quiet', text: 'Ver detalles' })
        .on('click', () => openRequestDetails(item.id))
        .appendTo($detailsActions);
      $row.append($detailsActions);

      if (item.can_decide) {
        const $actions = $('<div>', { class: 'request-actions' });
        $('<button>', { type: 'button', class: 'button primary', text: 'Aprobar' })
          .on('click', () => openDecisionDialog(item.id, 'approve'))
          .appendTo($actions);
        $('<button>', { type: 'button', class: 'button quiet reject', text: 'Rechazar' })
          .on('click', () => openDecisionDialog(item.id, 'reject'))
          .appendTo($actions);
        $row.append($actions);
      }

      if (item.can_cancel) {
        const $cancelActions = $('<div>', { class: 'request-actions' });
        $('<button>', { type: 'button', class: 'button quiet reject cancel', text: 'Cancelar' })
          .on('click', () => cancelRequest(item.id))
          .appendTo($cancelActions);
        $row.append($cancelActions);
      }

      $row.appendTo($list);
    });
  } catch (error) {
    $list.empty();
    showMessage(errorMessage(error), 'error');
  }
}

function openDecisionDialog(requestId, decision) {
  const dialog = document.getElementById('decision-dialog');
  const form = document.getElementById('decision-form');
  if (!dialog || !form) return;

  form.dataset.requestId = requestId;
  form.dataset.decision = decision;
  const isApproval = decision === 'approve';
  $('#decision-dialog-title').text(isApproval ? 'Aprobar solicitud' : 'Rechazar solicitud');
  $('#decision-dialog-description').text(isApproval
    ? 'Confirma la aprobación. Puedes agregar un comentario para el solicitante.'
    : 'Confirma el rechazo. Puedes explicar el motivo en un comentario para el solicitante.');
  $('#decision-confirm').text(isApproval ? 'Confirmar aprobación' : 'Confirmar rechazo').prop('disabled', false);
  $('#decision-comment').val('').prop('disabled', false);
  dialog.showModal();
  $('#decision-comment').trigger('focus');
}

function formatBytes(size) {
  const bytes = Number(size || 0);
  if (!Number.isFinite(bytes) || bytes <= 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  return `${(bytes / 1024).toFixed(1)} KB`;
}

function renderRequestDetails(item) {
  const $body = $('#request-details-body').empty();
  $('#request-details-title').text(item.site?.name || 'Detalle de la solicitud');

  const $general = $('<dl>', { class: 'request-details-grid' });
  const addField = (label, value) => {
    const $item = $('<div>');
    $item.append($('<dt>').text(label), $('<dd>').text(value || '—'));
    $general.append($item);
  };

  addField('Estado', labels[item.status] || item.status);
  addField('Solicitante', item.requester ? `${item.requester.name} · ${item.requester.email}` : '—');
  addField('Desde', item.requested_from ? formatDate(item.requested_from) : '—');
  addField('Hasta', item.requested_until ? formatDate(item.requested_until) : '—');
  if (item.decided_at) {
    addField('Decisión', `${item.decided_by?.name || '—'} · ${formatDate(item.decided_at)}`);
  }
  if (item.decision_comment) {
    const $comment = $('<div>', { class: 'wide' }).append(
      $('<dt>').text('Comentario de decisión'),
      $('<dd>').text(item.decision_comment),
    );
    $general.append($comment);
  }
  const $purpose = $('<div>', { class: 'wide' }).append(
    $('<dt>').text('Motivo'),
    $('<dd>').text(item.purpose || '—'),
  );
  $general.append($purpose);

  const $generalSection = $('<section>').append($('<h3>').text('Solicitud'), $general);
  $body.append($generalSection);

  const formatCustomValue = (value) => {
    if (Array.isArray(value)) return value.join(', ');
    if (value !== null && typeof value === 'object') return JSON.stringify(value);
    return value;
  };

  const formSchema = item.organization_form_version?.schema || item.form_version?.schema;
  const fields = formSchema?.fields || [];
  const coveredKeys = new Set(fields.map((field) => field.key));
  const orphanKeys = Object.keys(item.form_data || {}).filter((key) => !coveredKeys.has(key));
  if (fields.length || orphanKeys.length) {
    const $custom = $('<dl>', { class: 'request-details-grid' });
    fields.forEach((field) => {
      const attachment = (item.attachments || []).find((a) => a.form_field_key === field.key);
      const rawValue = field.type === 'file' ? attachment?.original_name : formatCustomValue(item.form_data?.[field.key]);
      const $field = $('<div>').append(
        $('<dt>').text(field.label || 'Campo'),
        $('<dd>').text(rawValue !== undefined && rawValue !== null && rawValue !== '' ? rawValue : '—'),
      );
      $custom.append($field);
    });
    orphanKeys.forEach((key) => {
      const rawValue = formatCustomValue(item.form_data[key]);
      if (rawValue === undefined || rawValue === null || rawValue === '') return;
      $custom.append($('<div>').append(
        $('<dt>').text(key),
        $('<dd>').text(rawValue),
      ));
    });
    $body.append($('<section>').append($('<h3>').text('Información adicional'), $custom));
  }

  const $peopleSection = $('<section>').append($('<h3>').text('Personas'));
  if ((item.people || []).length) {
    const $table = $('<table>', { class: 'request-details-people' });
    const $thead = $('<thead>').append($('<tr>').append(
      $('<th>').text('Nombre'), $('<th>').text('Documento'), $('<th>').text('Número'), $('<th>').text('Empresa'),
    ));
    const $tbody = $('<tbody>');
    (item.people || []).forEach((person) => {
      $tbody.append($('<tr>').append(
        $('<td>').text(person.full_name || '—'),
        $('<td>').text(person.document_type || '—'),
        $('<td>').text(person.document_number || '—'),
        $('<td>').text(person.company || '—'),
      ));
    });
    $table.append($thead, $tbody);
    $peopleSection.append($table);
  } else {
    $peopleSection.append($('<p>', { class: 'muted' }).text('No hay personas asociadas.'));
  }
  $body.append($peopleSection);

  const $filesSection = $('<section>').append($('<h3>').text('Adjuntos'));
  if ((item.attachments || []).length) {
    const $list = $('<ul>', { class: 'request-details-attachment-list' });
    (item.attachments || []).forEach((attachment) => {
      const label = `${attachment.original_name}${formatBytes(attachment.size_bytes) ? ` (${formatBytes(attachment.size_bytes)})` : ''}`;
      const $entry = $('<li>').append($('<span>').text(label));
      $('<button>', { type: 'button', class: 'button quiet', text: 'Descargar' })
        .on('click', () => downloadAttachment(item.id, attachment))
        .appendTo($entry);
      $list.append($entry);
    });
    $filesSection.append($list);
  } else {
    $filesSection.append($('<p>', { class: 'muted' }).text('Esta solicitud no tiene adjuntos.'));
  }
  $body.append($filesSection);
}

async function openRequestDetails(requestId) {
  const dialog = document.getElementById('request-details-dialog');
  if (!dialog) return;

  $('#request-details-body').empty().append($('<p>', { class: 'muted' }).text('Cargando detalle…'));
  dialog.showModal();

  try {
    await api('/sanctum/csrf-cookie');
    const result = await api(`/api/v1/access-requests/${encodeURIComponent(requestId)}`);
    renderRequestDetails(result.data || {});
  } catch (error) {
    dialog.close();
    showMessage(errorMessage(error), 'error');
  }
}

async function downloadAttachment(requestId, attachment) {
  const url = attachment.download_url || `/api/v1/access-requests/${encodeURIComponent(requestId)}/attachments/${encodeURIComponent(attachment.id)}`;

  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: '*/*',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
      },
    });

    if (!response.ok) {
      let message = 'No fue posible descargar el archivo.';
      try {
        const payload = await response.json();
        message = payload.message || Object.values(payload.errors || {}).flat()[0] || message;
      } catch (_) {
        // Respuesta binaria no legible como JSON; se conserva el mensaje genérico.
      }
      showMessage(message, 'error');
      return;
    }

    const blob = await response.blob();
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = attachment.original_name || 'archivo';
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(blobUrl), 4000);
  } catch (error) {
    showMessage(errorMessage(error), 'error');
  }
}

async function decideRequest(requestId, decision, comment, $row) {
  const $buttons = $row.find('.request-actions button').prop('disabled', true);
  const $confirm = $('#decision-confirm').prop('disabled', true);

  try {
    await api('/sanctum/csrf-cookie');
    await api(`/api/v1/access-requests/${encodeURIComponent(requestId)}/${decision}`, {
      method: 'POST',
      data: { comment },
    });
    document.getElementById('decision-dialog')?.close();
    showMessage(decision === 'approve' ? 'Solicitud aprobada.' : 'Solicitud rechazada.');
    await loadRequests();
  } catch (error) {
    showMessage(errorMessage(error), 'error');
    $buttons.prop('disabled', false);
    $confirm.prop('disabled', false);
  }
}

async function cancelRequest(requestId) {
  if (!window.confirm('¿Cancelar esta solicitud? Esta acción no se puede deshacer.')) return;

  const $row = $('.request-row').filter(function () {
    return this.dataset.requestId === requestId;
  }).first();
  const $buttons = $row.find('.request-actions button').prop('disabled', true);

  try {
    await api('/sanctum/csrf-cookie');
    await api(`/api/v1/access-requests/${encodeURIComponent(requestId)}/cancel`, {
      method: 'POST',
      data: {},
    });
    showMessage('Solicitud cancelada.');
    await loadRequests();
  } catch (error) {
    showMessage(errorMessage(error), 'error');
    $buttons.prop('disabled', false);
  }
}

async function submitRequest(event) {
  event.preventDefault();

  const form = event.currentTarget;
  validateRequestDateRange();
  if (!form.checkValidity()) {
    form.reportValidity();

    return;
  }

  const $form = $(form);
  const $button = $form.find('button[type="submit"]');
  const body = new FormData(form);

  $button.prop('disabled', true).text('Enviando…');

  try {
    await api('/sanctum/csrf-cookie');
    body.delete('attachments');
    body.set('purpose', form.elements.purpose.value.trim());
    body.set('requested_from', new Date(form.elements.requested_from.value).toISOString());
    body.set('requested_until', new Date(form.elements.requested_until.value).toISOString());
    Array.from(form.elements.attachments.files).forEach((file) => body.append('attachments[]', file));

    await api('/api/v1/access-requests', {
      method: 'POST',
      data: body,
      processData: false,
      contentType: false,
    });

    form.reset();
    validateRequestDateRange();
    $('#people-list [data-person]').slice(1).remove();
    $('#site_id').trigger('change');
    updatePersonRemoveButtons();
    showMessage('Solicitud enviada a aprobación.');
    await loadRequests();
  } catch (error) {
    showMessage(errorMessage(error), 'error');
  } finally {
    $button.prop('disabled', false).html('Enviar para aprobación <span aria-hidden="true">→</span>');
  }
}

$(() => {
  $('#people-list input[name^="people["]').each(function () {
    const match = this.name.match(/^people\[(\d+)\]/);
    if (match) nextPersonIndex = Math.max(nextPersonIndex, Number(match[1]) + 1);
  });

  $('#request-form').on('submit', submitRequest);
  $('#decision-form').on('submit', function (event) {
    event.preventDefault();

    const requestId = this.dataset.requestId;
    const decision = this.dataset.decision;
    const $row = $('.request-row').filter(function () {
      return this.dataset.requestId === requestId;
    }).first();

    if (!requestId || !['approve', 'reject'].includes(decision) || !$row.length) return;

    decideRequest(requestId, decision, $('#decision-comment').val().trim(), $row);
  });
  $('#decision-cancel').on('click', function () {
    document.getElementById('decision-dialog')?.close();
  });
  $('#request-details-close').on('click', function () {
    document.getElementById('request-details-dialog')?.close();
  });
  $('#request-form').on('input change', '[name="requested_from"], [name="requested_until"]', validateRequestDateRange);
  $('#custom-fields').on('input change', '[data-request-date]', function () { validateCustomDate(this); });
  $('#custom-fields').on('input', 'textarea[data-max-lines]', function () { validateCustomTextarea(this); });
  $('#refresh').on('click', loadRequests);
  $('#site_id').on('change', function () {
    renderCustomFields(siteForms.get(this.value));
  });
  $('#add-person').on('click', addPerson);
  $('#people-list').on('click', '.remove-person', function () {
    if ($('#people-list [data-person]').length > 1) {
      $(this).closest('[data-person]').remove();
      updatePersonRemoveButtons();
    }
  });

  if ($('#request-list').length) {
    validateRequestDateRange();
    loadSites();
    loadRequests();
    updatePersonRemoveButtons();
  }
});
