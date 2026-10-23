const labels = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Rechazada',
  cancelled: 'Cancelada',
};

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

    $select.empty().append($('<option>', {
      value: '',
      text: sites.length ? 'Selecciona un sitio' : 'No tienes sitios activos',
    }));

    sites.forEach((site) => $select.append($('<option>', { value: site.id, text: site.name })));
  } catch (error) {
    showMessage(errorMessage(error), 'error');
  }
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
      $row.append($info, $badge, $purpose).appendTo($list);
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
    body.set('people[0][full_name]', form.elements.person_name.value.trim());
    body.set('people[0][document_type]', form.elements.document_type.value.trim());
    body.set('people[0][document_number]', form.elements.document_number.value.trim());
    body.set('people[0][company]', form.elements.company.value.trim());
    Array.from(form.elements.attachments.files).forEach((file) => body.append('attachments[]', file));

    await api('/api/v1/access-requests', {
      method: 'POST',
      data: body,
      processData: false,
      contentType: false,
    });

    form.reset();
    showMessage('Solicitud enviada a aprobación.');
    await loadRequests();
  } catch (error) {
    showMessage(errorMessage(error), 'error');
  } finally {
    $button.prop('disabled', false).html('Enviar para aprobación <span aria-hidden="true">→</span>');
  }
}

$(() => {
  $('#request-form').on('submit', submitRequest);
  $('#refresh').on('click', loadRequests);

  if ($('#request-list').length) {
    loadSites();
    loadRequests();
  }
});
