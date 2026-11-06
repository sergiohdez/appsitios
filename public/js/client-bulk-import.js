(() => {
  const batchSize = 50;
  const pageSize = 100;

  function responseMessage(payload) {
    const errors = payload?.errors;
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors).flat().find((message) => typeof message === 'string');
      if (first) return first;
    }

    return payload?.message || 'No se pudo procesar la solicitud.';
  }

  document.querySelectorAll('[data-client-bulk-import]').forEach((panel) => {
    const form = panel.querySelector('.bulk-import-upload');
    const fileInput = form.querySelector('input[type="file"]');
    const uploadButton = form.querySelector('button[type="submit"]');
    const message = panel.querySelector('.bulk-import-message');
    const progressPanel = panel.querySelector('.bulk-import-progress');
    const batchLabel = panel.querySelector('.bulk-import-batch');
    const currentLabel = panel.querySelector('.bulk-import-current');
    const progressBar = panel.querySelector('.bulk-import-bar');
    const summary = panel.querySelector('.bulk-import-summary');
    const results = panel.querySelector('.bulk-import-results');
    const startButton = panel.querySelector('.bulk-import-start');
    const errorsLink = panel.querySelector('.bulk-import-errors');
    const pager = panel.querySelector('.bulk-import-pager');
    const prevPageButton = panel.querySelector('.bulk-import-page-prev');
    const nextPageButton = panel.querySelector('.bulk-import-page-next');
    const pageInfo = panel.querySelector('.bulk-import-page-info');
    const csrf = form.querySelector('[name="_token"]').value;
    let importState = null;
    let nextUrl = null;
    let running = false;
    let resultEntries = [];
    let currentPage = 0;

    function showMessage(text, kind = 'muted') {
      message.textContent = text;
      message.className = `bulk-import-message ${kind}`;
    }

    function renderResults() {
      const totalPages = Math.max(1, Math.ceil(resultEntries.length / pageSize));
      currentPage = Math.min(Math.max(0, currentPage), totalPages - 1);

      results.replaceChildren();
      const start = currentPage * pageSize;
      const slice = resultEntries.slice(start, start + pageSize);
      for (const entry of slice) {
        const item = document.createElement('li');
        item.className = entry.kind === 'error' ? 'bulk-import-error' : 'bulk-import-warning';
        item.textContent = entry.text;
        results.append(item);
      }

      if (resultEntries.length > pageSize) {
        pager.hidden = false;
        pageInfo.textContent = `Mostrando ${start + 1}–${Math.min(start + pageSize, resultEntries.length)} de ${resultEntries.length} registros`;
        prevPageButton.disabled = currentPage === 0;
        nextPageButton.disabled = currentPage >= totalPages - 1;
      } else {
        pager.hidden = true;
        pageInfo.textContent = '';
        prevPageButton.disabled = true;
        nextPageButton.disabled = true;
      }
    }

    function appendResult(text, kind) {
      resultEntries.push({ text, kind });
      renderResults();
    }

    if (prevPageButton && nextPageButton) {
      prevPageButton.addEventListener('click', () => {
        if (currentPage > 0) {
          currentPage--;
          renderResults();
        }
      });
      nextPageButton.addEventListener('click', () => {
        const totalPages = Math.ceil(resultEntries.length / pageSize);
        if (currentPage < totalPages - 1) {
          currentPage++;
          renderResults();
        }
      });
    }

    async function post(url, body) {
      const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok) throw new Error(responseMessage(payload));
      return payload;
    }

    function updateSummary(state) {
      progressBar.max = state.total_records;
      progressBar.value = state.processed_records;
      summary.textContent = `${state.successful_records} importados correctamente · ${state.failed_records} con errores · ${state.processed_records} de ${state.total_records} procesados`;

      if (state.errors_url && state.failed_records > 0) {
        errorsLink.href = state.errors_url;
        errorsLink.hidden = false;
      }
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!fileInput.files.length) return;

      uploadButton.disabled = true;
      startButton.disabled = true;
      showMessage('Leyendo el archivo y contando registros…');

      try {
        const payload = await post(panel.dataset.previewUrl, new FormData(form));
        importState = {
          ...payload,
          processed_records: 0,
          successful_records: 0,
          failed_records: 0,
        };
        nextUrl = payload.next_url;
        progressPanel.hidden = false;
        resultEntries = [];
        currentPage = 0;
        renderResults();
        errorsLink.hidden = true;
        progressBar.max = payload.total_records;
        progressBar.value = 0;
        currentLabel.textContent = '';
        batchLabel.textContent = '';
        summary.textContent = `0 importados correctamente · 0 con errores · 0 de ${payload.total_records} procesados`;
        startButton.hidden = false;
        startButton.disabled = false;
        startButton.textContent = 'Iniciar importación';
        showMessage(`Se encontraron ${payload.total_records} registros para importar.`);
      } catch (error) {
        showMessage(error.message, 'notice error');
        uploadButton.disabled = false;
        startButton.disabled = false;
      }
    });

    startButton.addEventListener('click', async () => {
      if (!importState || running) return;
      running = true;
      startButton.disabled = true;
      uploadButton.disabled = true;
      startButton.textContent = 'Importando…';
      const totalBatches = Math.ceil(importState.total_records / batchSize);

      try {
        while ((importState.processed_records ?? 0) < importState.total_records) {
          const firstInBatch = (importState.processed_records ?? 0) + 1;
          const lastInBatch = Math.min(firstInBatch + batchSize - 1, importState.total_records);
          const batchNumber = Math.ceil(firstInBatch / batchSize);
          batchLabel.textContent = totalBatches > 1
            ? `Lote ${batchNumber} de ${totalBatches} · registros ${firstInBatch}–${lastInBatch}`
            : '';

          for (let item = 0; item < batchSize && (importState.processed_records ?? 0) < importState.total_records; item++) {
            currentLabel.textContent = `Procesando registro ${(importState.processed_records ?? 0) + 1} de ${importState.total_records}`;
            importState = await post(nextUrl, new FormData());
            updateSummary(importState);

            if (importState.record && !importState.record.imported) {
              appendResult(`Fila ${importState.record.source_row}: ${importState.record.errors.join(' ')}`, 'error');
            }

            for (const warning of importState.record?.warnings || []) {
              appendResult(`Fila ${importState.record.source_row}: ${warning}`, 'warning');
            }
          }
        }

        currentLabel.textContent = 'Importación finalizada.';
        batchLabel.textContent = '';
        showMessage('La importación terminó. Puedes descargar el archivo de registros fallidos para corregirlos y cargarlos de nuevo.', 'notice success');
        startButton.hidden = true;
      } catch (error) {
        currentLabel.textContent = '';
        showMessage(`${error.message} Puedes continuar la importación desde el último registro confirmado.`, 'notice error');
        startButton.disabled = false;
        startButton.textContent = 'Continuar importación';
      } finally {
        running = false;
        uploadButton.disabled = false;
        if (!startButton.hidden && !startButton.textContent.includes('Continuar')) {
          startButton.textContent = 'Iniciar importación';
        }
      }
    });
  });
})();
