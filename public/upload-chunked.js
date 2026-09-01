const ChunkedUpload = (() => {
  let container = null;
  let idCounter = 0;
  const CHUNK_SIZE = 5 * 1024 * 1024;

  function getMimeType(file) {
    if (file.type) return file.type;

    const extension = file.name.split('.').pop().toLowerCase();

    const mimeTypes = {
        rar: 'application/vnd.rar',
        zip: 'application/zip',
        '7z': 'application/x-7z-compressed',
        tar: 'application/x-tar',
        gz: 'application/gzip'
    };

    return mimeTypes[extension] || 'application/octet-stream';
  }

  function injectStyles() {
    if (document.getElementById('upload-progress-css')) return;
    const style = document.createElement('style');
    style.id = 'upload-progress-css';
    style.textContent = `
      .up-container { position: fixed; bottom: 16px; right: 16px; z-index: 9999; width: 320px; font-family: inherit; display: flex; flex-direction: column-reverse; gap: 6px; pointer-events: none; }
      .up-container:empty { display: none; }
      .up-item { background: #1a1a2e; border: 1px solid #2a2a4a; border-radius: 8px; padding: 10px 12px; pointer-events: auto; box-shadow: 0 4px 12px rgba(0,0,0,.4); animation: up-slide-in .2s ease-out; }
      .up-item.up-done { opacity: 0; transform: translateX(20px); transition: .3s; pointer-events: none; }
      .up-item.up-error { border-color: #ef4444; }
      .up-row { display: flex; align-items: center; gap: 8px; }
      .up-icon { flex-shrink: 0; width: 16px; height: 16px; color: #6366f1; }
      .up-item.up-done .up-icon { color: #22c55e; }
      .up-item.up-error .up-icon { color: #ef4444; }
      .up-name { flex: 1; font-size: .75rem; color: #e2e8f0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .up-pct { font-size: .7rem; color: #94a3b8; min-width: 36px; text-align: right; }
      .up-cancel { background: none; border: none; color: #64748b; cursor: pointer; padding: 2px; display: flex; }
      .up-cancel:hover { color: #ef4444; }
      .up-cancel svg { width: 14px; height: 14px; }
      .up-bar-bg { height: 3px; background: #2a2a4a; border-radius: 2px; margin-top: 6px; overflow: hidden; }
      .up-bar-fill { height: 100%; background: #6366f1; border-radius: 2px; transition: width .15s; }
      .up-item.up-done .up-bar-fill { background: #22c55e; }
      .up-item.up-error .up-bar-fill { background: #ef4444; }
      .up-spinner { display: none; width: 14px; height: 14px; border: 2px solid #2a2a4a; border-top-color: #6366f1; border-radius: 50%; animation: up-spin .6s linear infinite; }
      .up-item.up-processing .up-spinner { display: block; }
      .up-item.up-processing .up-cancel { display: none; }
      @keyframes up-spin { to { transform: rotate(360deg); } }
      @keyframes up-slide-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    `;
    document.head.appendChild(style);
  }

  function ensureContainer() {
    if (container) return;
    injectStyles();
    container = document.createElement('div');
    container.className = 'up-container';
    document.body.appendChild(container);
  }

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function createItem(id, name) {
    const el = document.createElement('div');
    el.className = 'up-item';
    el.id = `up-${id}`;
    el.innerHTML = `
      <div class="up-row">
        <svg class="up-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span class="up-name">${esc(name)}</span>
        <span class="up-pct">0%</span>
        <div class="up-spinner"></div>
        <button class="up-cancel" title="Cancelar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="up-bar-bg"><div class="up-bar-fill" style="width:0%"></div></div>`;
    return el;
  }

  function updateItem(id, pct, done, error, processing) {
    const el = document.getElementById(`up-${id}`);
    if (!el) return;
    if (processing) {
      el.classList.add('up-processing');
      el.querySelector('.up-pct').textContent = '';
      el.querySelector('.up-bar-fill').style.width = '100%';
      return;
    }
    el.classList.remove('up-processing');
    el.querySelector('.up-pct').textContent = done ? (error ? 'Error' : '100%') : `${pct}%`;
    el.querySelector('.up-bar-fill').style.width = (error ? 100 : done ? 100 : pct) + '%';
    if (done || error) {
      el.classList.add(done ? 'up-done' : 'up-error');
      setTimeout(() => el.remove(), 1500);
    }
  }

  function refreshSession() {
    const base = typeof API !== 'undefined' ? API : '/api';
    return fetch(`${base}/refresh`, { method: 'POST', credentials: 'same-origin' })
      .then(r => r.ok)
      .catch(() => false);
  }

  function apiFetch(url, options = {}) {
    return fetch(url, { credentials: 'same-origin', ...options });
  }

  function apiFetchWithRetry(url, options = {}, ac) {
    return apiFetch(url, options).then(r => {
      if (r.status === 401) {
        return refreshSession().then(ok => {
          if (!ok) throw new Error('Sesión expirada');
          return apiFetch(url, options);
        });
      }
      return r;
    });
  }
  
  function initUpload(fileName, mimeType, totalSize, folderId) {
      console.log({
        name: fileName,
        type: mimeType,
        size: totalSize,
        folder: folderId
    });
    const base = typeof API !== 'undefined' ? API : '/api';
    return apiFetchWithRetry(`${base}/storage/upload/init`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file_name: fileName, mime_type: mimeType, total_size: totalSize, folder_id: folderId })
    }).then(r => {
      if (!r.ok) throw new Error('Error al iniciar upload');
      return r.json();
    });
  }

  function sendChunk(sessionId, chunk, partNumber, totalParts, ac) {
    const base = typeof API !== 'undefined' ? API : '/api';
    const fd = new FormData();
    fd.append('chunk', chunk);
    fd.append('part_number', partNumber);
    fd.append('total_parts', totalParts);
    return apiFetchWithRetry(`${base}/storage/upload/${sessionId}/chunk`, {
      method: 'PUT',
      body: fd,
      signal: ac.signal
    }).then(r => {
      if (!r.ok) throw new Error(`Error en chunk ${partNumber}`);
      return r.json();
    });
  }

  function completeUpload(sessionId, fileName, mimeType, folderId) {
    const base = typeof API !== 'undefined' ? API : '/api';
    return apiFetchWithRetry(`${base}/storage/upload/${sessionId}/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file_name: fileName, mime_type: mimeType, folder_id: folderId })
    }).then(r => {
      if (!r.ok) throw new Error('Error al completar upload');
      return r.json();
    });
  }

  function abortUpload(sessionId) {
    const base = typeof API !== 'undefined' ? API : '/api';
    apiFetch(`${base}/storage/upload/${sessionId}/abort`, { method: 'POST' }).catch(() => {});
  }

  function upload(file, folderId = null) {

    ensureContainer();
    const id = ++idCounter;
    const el = createItem(id, file.name);
    container.prepend(el);
    const ac = new AbortController();
    el.querySelector('.up-cancel').onclick = () => ac.abort();

    let sessionId = null;

    return new Promise((resolve, reject) => {
      const onAbort = () => {
        if (sessionId) abortUpload(sessionId);
        el.remove();
        reject(new Error('Cancelado'));
      };
      ac.signal.addEventListener('abort', onAbort);

      const totalParts = Math.ceil(file.size / CHUNK_SIZE);
      const mimeType = getMimeType(file);

      initUpload(file.name, mimeType, file.size, folderId)
        .then(init => {
          sessionId = init.session_id;
          const parts = init.total_parts || totalParts;
          const chunkSize = init.chunk_size || CHUNK_SIZE;
          let current = 0;

          function sendNext() {
            if (current >= parts) {
              return completeUpload(sessionId, file.name, mimeType, folderId).then(result => {
                updateItem(id, 100, true);
                resolve(result);
              });
            }
            current++;
            const start = (current - 1) * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);
            const pct = Math.round((current / parts) * 100);
            if (current === parts) {
              updateItem(id, 100, false, false, true);
            } else {
              updateItem(id, pct);
            }
            return sendChunk(sessionId, chunk, current, parts, ac).then(() => sendNext());
          }

          return sendNext();
        })
        .catch(err => {
          if (ac.signal.aborted) return;
          if (sessionId) abortUpload(sessionId);
          updateItem(id, 0, false, true);
          reject(err);
        });
    });
  }

  return { upload };
})();
