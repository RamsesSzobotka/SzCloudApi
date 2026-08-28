// Upload Progress Bar — XHR-based with concurrent upload support
// Usage: UploadProgress.upload(file, folderId).then(result => ...)

const UploadProgress = (() => {
  let container = null;
  let idCounter = 0;

  function injectStyles() {
    if (document.getElementById('upload-progress-css')) return;
    const style = document.createElement('style');
    style.id = 'upload-progress-css';
    style.textContent = `
      .up-container {
        position: fixed; bottom: 16px; right: 16px; z-index: 9999;
        width: 320px; font-family: inherit;
        display: flex; flex-direction: column-reverse; gap: 6px;
        pointer-events: none;
      }
      .up-container:empty { display: none; }
      .up-item {
        background: #1a1a2e; border: 1px solid #2a2a4a; border-radius: 8px;
        padding: 10px 12px; pointer-events: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,.4);
        animation: up-slide-in .2s ease-out;
      }
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

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

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

  function upload(file, folderId = null) {
    ensureContainer();

    const id = ++idCounter;
    const el = createItem(id, file.name);
    container.prepend(el);

    const xhr = new XMLHttpRequest();
    el.querySelector('.up-cancel').onclick = () => xhr.abort();
    let retried = false;

    function resetUI() {
      el.classList.remove('up-processing', 'up-error');
      el.querySelector('.up-pct').textContent = '0%';
      el.querySelector('.up-bar-fill').style.width = '0%';
    }

    return new Promise((resolve, reject) => {
      function send() {
        const fd = new FormData();
        fd.append('file', file);
        if (folderId) fd.append('folder_id', folderId);

        xhr.upload.onprogress = e => {
          if (e.lengthComputable) {
            const pct = Math.round(e.loaded / e.total * 100);
            if (pct >= 100) {
              updateItem(id, 100, false, false, true);
            } else {
              updateItem(id, pct);
            }
          }
        };

        xhr.onload = () => {
          if (xhr.status === 401 && !retried) {
            retried = true;
            refreshSession().then(ok => {
              if (ok) {
                resetUI();
                send();
              } else {
                updateItem(id, 0, false, true);
                reject(new Error('Sesión expirada'));
              }
            });
            return;
          }
          if (xhr.status >= 200 && xhr.status < 300) {
            updateItem(id, 100, true);
            try { resolve(JSON.parse(xhr.responseText)); } catch { resolve(xhr.responseText); }
          } else {
            updateItem(id, 0, false, true);
            let msg = 'Error';
            try { msg = JSON.parse(xhr.responseText)?.message || msg; } catch {}
            reject(new Error(msg));
          }
        };

        xhr.onerror = () => {
          updateItem(id, 0, false, true);
          reject(new Error('Error de red'));
        };

        xhr.onabort = () => {
          el.remove();
          reject(new Error('Cancelado'));
        };

        xhr.open('POST', `${typeof API !== 'undefined' ? API : '/api'}/storage/file`);
        xhr.withCredentials = true;
        xhr.send(fd);
      }

      send();
    });
  }

  return { upload };
})();
