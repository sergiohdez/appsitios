const labels = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Rechazada',
  cancelled: 'Cancelada',
};

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

  return payload.message || firstValidationError || 'No fue posible completar la operación.';
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

  $('#form_version_id').val(site?.form_version_id || '');

  if (!fields.length) {
    return;
  }

  $('<div>', { class: 'custom-fields-heading' })
    .append($('<p>', { class: 'eyebrow' }).text('CAMPOS DEL SITIO'))
    .append($('<h3>').text('Información adicional'))
    .appendTo($container);

  fields.forEach((field) => {
    const required = Boolean(field.required);
    const $label = $('<label>').text(`${field.label}${required ? ' · Obligatorio' : ''}`);
    const name = `form_data[${field.key}]`;
    let $input;

    if (field.type === 'select') {
      $input = $('<select>', { name, required }).append($('<option>', { value: '', text: 'Selecciona una opción' }));
      (field.options || []).forEach((option) => $input.append($('<option>', { value: option, text: option })));
    } else if (field.type === 'textarea') {
      $input = $('<textarea>', { name, rows: 3, maxlength: 10000, required });
    } else if (field.type === 'file') {
      $input = $('<input>', { name, type: 'file', accept: '.pdf,.jpg,.jpeg,.png', required });
    } else if (field.type === 'date') {
      $input = $('<input>', { name, type: 'date', required });
    } else {
      $input = $('<input>', { name, type: 'text', maxlength: 2000, required });
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
  const markup = `
    <fieldset class="person-card" data-person>
      <legend class="field-title">Persona que ingresará</legend>
      <label>Nombre completo<input name="people[${index}][full_name]" maxlength="180" autocomplete="off" required></label>
      <div class="two-columns">
        <label>Tipo de documento<input name="people[${index}][document_type]" maxlength="40" placeholder="CC, pasaporte…"></label>
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
      const $row = $('<article>', { class: 'request-row' });
      const $info = $('<div>');
      const $title = $('<p>', { class: 'request-title' }).text(item.site?.name || 'Sitio');
      const $meta = $('<p>', { class: 'request-meta' }).text(`${formatDate(item.requested_from)} · ${item.people?.length || 0} persona(s)`);
      const $purpose = $('<p>', { class: 'request-purpose' }).text(item.purpose);
      const $badge = $('<span>', { class: `badge ${item.status}` }).text(labels[item.status] || item.status);

      $info.append($title, $meta);
      $row.append($info, $badge, $purpose);

      (item.form_version?.schema?.fields || []).forEach((field) => {
        const attachment = (item.attachments || []).find((itemAttachment) => itemAttachment.form_field_key === field.key);
        const value = field.type === 'file' ? attachment?.original_name : item.form_data?.[field.key];

        if (value !== undefined && value !== null && value !== '') {
          $row.append($('<p>', { class: 'request-purpose custom-request-value' }).text(`${field.label}: ${value}`));
        }
      });

      $row.appendTo($list);
    });
  } catch (error) {
    $list.empty();
    showMessage(errorMessage(error), 'error');
  }
}

async function submitRequest(event) {
  event.preventDefault();

  const form = event.currentTarget;
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
    loadSites();
    loadRequests();
    updatePersonRemoveButtons();
  }
});
