const API = '/api';
const BASE_URL = window.location.origin;
let currentFolderId = null;
let currentView = 'files';
let currentPage = { folders: 1, files: 1 };
let currentItems = { folders: [], files: [] };
let moveTarget = null;
let moveSelectedFolder = '';
let propsData = null;
let propsType = null;
let folderHierarchy = null;

const $ = id => document.getElementById(id);

// ── Welcome Modal ──
function openWelcomeModal() { $('welcome-modal').classList.remove('hidden'); }
function closeWelcomeModal() {
  $('welcome-modal').classList.add('hidden');
  localStorage.setItem('szcloud_welcome_seen', '1');
}
window.addEventListener('DOMContentLoaded', () => {
  if (!localStorage.getItem('szcloud_welcome_seen')) openWelcomeModal();
});

const toast = (msg, icon = 'success') => {
  Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 2000, timerProgressBar: true, background: '#1a1a2e', color: '#e2e8f0', border: '1px solid #2a2a4a' }).fire({ icon, title: msg });
};

function log(msg, cls = '') {
  const el = $('log');
  const line = document.createElement('div');
  line.className = cls;
  line.textContent = msg;
  el.appendChild(line);
  if (el.children.length > 200) el.firstChild.remove();
  el.scrollTop = el.scrollHeight;
}
function logJson(label, data, ok = true) { log(`${label} ${JSON.stringify(data, null, 2).substring(0, 500)}`, ok ? 'log-ok' : 'log-err'); }

// ── SVG Icons ──
const ICONS = {
  folder: '<svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
  file: '<svg viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  image: '<svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
  music: '<svg viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  video: '<svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
  archive: '<svg viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
  code: '<svg viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  json: '<svg viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  document: '<svg viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  gear: '<svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
  download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  restore: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
  move: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>',
  more: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
  versions: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  activity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
  chevronRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>',
  arrowLeft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
  arrowRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
  close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  permDelete: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
  remove: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
  upload: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
};

function getFileIcon(ext, mime) {
  const e = (ext || '').toLowerCase();
  if (!e && !mime) return ICONS.file;
  if (['jpg','jpeg','png','gif','svg','webp','bmp','ico'].includes(e) || (mime && mime.startsWith('image/'))) return ICONS.image;
  if (['mp3','wav','ogg','flac','aac','m4a'].includes(e) || (mime && mime.startsWith('audio/'))) return ICONS.music;
  if (['mp4','avi','mkv','mov','webm','flv'].includes(e) || (mime && mime.startsWith('video/'))) return ICONS.video;
  if (['zip','rar','7z','tar','gz','bz2','xz'].includes(e) || (mime && mime.includes('zip'))) return ICONS.archive;
  if (['js','ts','py','go','rs','php','html','css','jsx','tsx','vue','rb','java','c','cpp','h','sh','bat'].includes(e)) return ICONS.code;
  if (['json','xml','yaml','yml','toml','ini','cfg','conf'].includes(e)) return ICONS.json;
  if (['pdf'].includes(e) || (mime && mime.includes('pdf'))) return ICONS.document;
  if (['exe','dmg','msi','app'].includes(e)) return ICONS.gear;
  if (mime && (mime.includes('json') || mime.includes('xml'))) return ICONS.json;
  return ICONS.file;
}

function fmtSize(b) {
  if (b == null) return '';
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
  return (b/1073741824).toFixed(2) + ' GB';
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function fmtDate(s) { if (!s) return ''; try { return new Date(s).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'2-digit' }); } catch { return s; } }

// ── Name validation (mirrors NameSanitizer.php) ──
const INVALID_NAME_CHARS = ['/', '\\', ':', '"', "'", '<', '>', '|'];
function isValidName(name) {
  const found = INVALID_NAME_CHARS.filter(c => name.includes(c));
  return { valid: found.length === 0, invalidChars: found };
}
function showInvalidNameError(name) {
  const { invalidChars } = isValidName(name);
  const chars = invalidChars.map(c => `<code>${esc(c)}</code>`).join(' ');
  Swal.fire({ icon: 'error', title: 'Nombre no válido', html: `No se pueden usar los caracteres: ${chars}`, background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
}

// ═══════════════════════════════════════════
//  STATUS & AUTH
// ═══════════════════════════════════════════

async function updateStatus() {
  const b = $('status-badge');
  const btnLogin = $('btn-login');
  const btnLogout = $('btn-logout');
  try {
    let res = await fetch(`${API}/me`, { credentials: 'same-origin' });
    if (res.status === 401) {
      const refreshed = await tryRefresh();
      if (refreshed) res = await fetch(`${API}/me`, { credentials: 'same-origin' });
    }
    if (res.ok) {
      b.innerHTML = '<span class="status-dot"></span>conectado'; b.className = 'status connected';
      if (btnLogin) btnLogin.style.display = 'none';
      if (btnLogout) btnLogout.style.display = '';
    } else {
      b.innerHTML = '<span class="status-dot"></span>sin token'; b.className = 'status disconnected';
      if (btnLogin) btnLogin.style.display = '';
      if (btnLogout) btnLogout.style.display = 'none';
    }
  } catch {
    b.innerHTML = '<span class="status-dot"></span>sin token'; b.className = 'status disconnected';
    if (btnLogin) btnLogin.style.display = '';
    if (btnLogout) btnLogout.style.display = 'none';
  }
}

function openLoginModal() { $('login-modal').classList.remove('hidden'); $('login-email').focus(); }
function closeLoginModal() { $('login-modal').classList.add('hidden'); }

async function doRegister() {
  const email = $('login-email').value;
  const pass = $('login-pass').value;
  if (!email || !pass) return Swal.fire({ icon: 'warning', title: 'Completa los campos', background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
  closeLoginModal();
  Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#e2e8f0' });
  const d = await apiCall('POST', '/register', { name: email.split('@')[0], email, password: pass, password_confirmation: pass });
  Swal.close();
  if (d) { await updateStatus(); toast('Registrado'); loadStorageInfo(); browseRoot(); }
}

async function doLogin() {
  const email = $('login-email').value;
  const pass = $('login-pass').value;
  if (!email || !pass) return Swal.fire({ icon: 'warning', title: 'Completa los campos', background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
  closeLoginModal();
  Swal.fire({ title: 'Iniciando sesión...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), background: '#1a1a2e', color: '#e2e8f0' });
  const d = await apiCall('POST', '/login', { email, password: pass });
  Swal.close();
  if (d) { await updateStatus(); toast('Sesión iniciada'); loadStorageInfo(); browseRoot(); }
}

async function doLogout() {
  const result = await Swal.fire({
    title: '¿Cerrar sesión?', icon: 'question', showCancelButton: true,
    confirmButtonText: 'Sí, salir', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (!result.isConfirmed) return;
  await apiCall('POST', '/logout');
  await updateStatus(); toast('Desconectado');
  currentView = 'files'; currentFolderId = null;
  $('file-grid').innerHTML = ''; $('pagination').innerHTML = '';
}

// ═══════════════════════════════════════════
//  RESPONSE PANEL HELPER
// ═══════════════════════════════════════════

function showResponse(method, path, status, statusText, elapsed, data, contentType, headers) {
  const statusEl = $('res-status');
  statusEl.textContent = `${status} ${statusText}`;
  statusEl.className = 'res-status ' + (status >= 200 && status < 300 ? 'ok' : 'err');
  $('res-time').textContent = `${elapsed}ms`;

  const bodyEl = $('res-body');
  if (contentType && contentType.includes('json')) {
    bodyEl.textContent = JSON.stringify(data, null, 2);
  } else {
    bodyEl.textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
  }

  const headersEl = $('res-headers');
  if (headers) {
    let headersText = '';
    headers.forEach((v, k) => { headersText += `${k}: ${v}\n`; });
    headersEl.textContent = headersText;
  }
}

// ═══════════════════════════════════════════
//  API CALL HELPER (for file browser)
// ═══════════════════════════════════════════

async function tryRefresh() {
  try {
    log('> POST /refresh', 'log-info');
    const res = await fetch(`${API}/refresh`, { method: 'POST', credentials: 'same-origin' });
    if (res.ok) { log('[200] Token renovado', 'log-ok'); return true; }
    log(`[${res.status}] Refresh fallido`, 'log-err');
    return false;
  } catch (e) { log(`Error: ${e.message}`, 'log-err'); return false; }
}

async function apiCall(method, path, body = null, isForm = false, retried = false) {
  const opts = { method, headers: {}, credentials: 'same-origin' };
  if (body && !isForm) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  else if (body && isForm) { opts.body = body; }
  log(`> ${method} ${path}`, 'log-info');
  const startTime = performance.now();
  try {
    const res = await fetch(`${API}${path}`, opts);
    const elapsed = Math.round(performance.now() - startTime);
    if (res.status === 401 && !retried && !['/login', '/register', '/refresh'].includes(path)) {
      const refreshed = await tryRefresh();
      if (refreshed) return apiCall(method, path, body, isForm, true);
    }
    const ct = res.headers.get('content-type') || '';
    const data = ct.includes('json') ? await res.json() : await res.text();
    showResponse(method, path, res.status, res.statusText, elapsed, data, ct, res.headers);
    if (!res.ok) { logJson(`[${res.status}]`, data, false); return null; }
    logJson(`[${res.status}]`, data);
    return data;
  } catch (e) {
    const elapsed = Math.round(performance.now() - startTime);
    showResponse(method, path, 0, 'Error', elapsed, e.message, null, null);
    log(`Error: ${e.message}`, 'log-err');
    return null;
  }
}

// ═══════════════════════════════════════════
//  API TESTER (center panel)
// ═══════════════════════════════════════════

async function sendRequest() {
  const method = $('req-method').value;
  const url = $('req-url').value.trim();
  if (!url) return toast('Ingresa una ruta', 'warning');

  // What's in the input IS the URL to fetch - no transformation
  const fetchUrl = url.startsWith('http') ? url : `${BASE_URL}${url.startsWith('/') ? '' : '/'}${url}`;

  // Build headers
  const headers = {};
  document.querySelectorAll('#header-fields .header-row').forEach(row => {
    const inputs = row.querySelectorAll('input');
    if (inputs[0].value.trim() && inputs[1].value.trim()) {
      headers[inputs[0].value.trim()] = inputs[1].value.trim();
    }
  });

  // Build body
  let body = null;
  const bodyType = document.querySelector('input[name="body-type"]:checked')?.value || 'json';
  if (method !== 'GET' && method !== 'DELETE') {
    if (bodyType === 'json') {
      const raw = $('req-body').value.trim();
      if (raw) {
        try { body = JSON.parse(raw); } catch { log('JSON inválido en body', 'log-err'); return; }
      }
    } else if (bodyType === 'form') {
      body = new FormData();
      document.querySelectorAll('#form-fields .form-row').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs[0].value.trim()) body.append(inputs[0].value.trim(), inputs[1].value);
      });
    }
  }

  // Fire the request
  const startTime = performance.now();
  log(`> ${method} ${url}`, 'log-info');

  try {
    const opts = { method, headers: { ...headers }, credentials: 'same-origin' };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }

    const res = await fetch(fetchUrl, opts);
    const elapsed = Math.round(performance.now() - startTime);
    const ct = res.headers.get('content-type') || '';
    const data = ct.includes('json') ? await res.json() : await res.text();

    showResponse(method, url, res.status, res.statusText, elapsed, data, ct, res.headers);
    logJson(`[${res.status}]`, data, res.ok);
  } catch (e) {
    const elapsed = Math.round(performance.now() - startTime);
    showResponse(method, url, 0, 'Error', elapsed, e.message, null, null);
    log(`Error: ${e.message}`, 'log-err');
  }
}

function quickAuth(method, path) {
  $('req-method').value = method;
  $('req-url').value = `${BASE_URL}/api${path}`;
  if (method === 'GET' || method === 'DELETE') {
    document.querySelector('input[name="body-type"][value="none"]').checked = true;
    switchBodyType('none');
  }
  sendRequest();
}

// ── Request Tabs ──
function switchReqTab(tab, el) {
  document.querySelectorAll('.req-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  $('req-body-pane').style.display = tab === 'body' ? '' : 'none';
  $('req-headers-pane').style.display = tab === 'headers' ? '' : 'none';
  $('req-auth-pane').style.display = tab === 'auth' ? '' : 'none';
  $('req-helpers-pane').style.display = tab === 'helpers' ? '' : 'none';
  if (tab === 'body') $('req-body-pane').classList.add('active');
  if (tab === 'headers') $('req-headers-pane').classList.add('active');
  if (tab === 'auth') $('req-auth-pane').classList.add('active');
  if (tab === 'helpers') $('req-helpers-pane').classList.add('active');
}

// ── Body Type ──
function switchBodyType(type) {
  $('body-json-editor').style.display = type === 'json' ? '' : 'none';
  $('body-form-editor').style.display = type === 'form' ? '' : 'none';
}

// ── Dynamic Form Fields ──
function addFormField(key = '', value = '') {
  const container = $('form-fields');
  const row = document.createElement('div');
  row.className = 'form-row';
  row.innerHTML = `
    <input type="text" placeholder="Key" value="${esc(key)}">
    <input type="text" placeholder="Value" value="${esc(value)}">
    <button class="remove-btn" onclick="this.parentElement.remove()">${ICONS.remove}</button>`;
  container.appendChild(row);
}

function addHeaderField(key = '', value = '') {
  const container = $('header-fields');
  const row = document.createElement('div');
  row.className = 'header-row';
  row.innerHTML = `
    <input type="text" placeholder="Header name" value="${esc(key)}">
    <input type="text" placeholder="Value" value="${esc(value)}">
    <button class="remove-btn" onclick="this.parentElement.remove()">${ICONS.remove}</button>`;
  container.appendChild(row);
}

function clearResponse() {
  $('res-status').textContent = '';
  $('res-status').className = 'res-status';
  $('res-time').textContent = '';
  $('res-body').innerHTML = '<div class="empty-response">Envía una request para ver la respuesta</div>';
  $('res-headers').innerHTML = '';
}

// ── Response Tabs ──
function switchResTab(tab, el) {
  document.querySelectorAll('.res-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  $('res-body').style.display = tab === 'body' ? '' : 'none';
  $('res-headers').style.display = tab === 'headers' ? '' : 'none';
}

// ═══════════════════════════════════════════
//  STORAGE INFO
// ═══════════════════════════════════════════

async function loadStorageInfo() {
  const d = await apiCall('GET', '/storage/info');
  if (!d) return;
  const bar = $('storage-info-bar');
  bar.style.display = 'block';
  const used = d.storage_used || 0;
  const limit = d.storage_limit || 1;
  const pct = Math.min(100, (used / limit * 100)).toFixed(1);
  $('storage-fill').style.width = pct + '%';
  $('storage-fill').style.background = pct > 90 ? '#ef4444' : '#6366f1';
  const fmt = b => b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : b < 1073741824 ? (b/1048576).toFixed(1) + ' MB' : (b/1073741824).toFixed(2) + ' GB';
  $('storage-text').textContent = `${fmt(used)} / ${fmt(limit)} (${pct}%) — Archivos: ${d.file_count ?? 0}`;
}

// ═══════════════════════════════════════════
//  FILE BROWSER
// ═══════════════════════════════════════════

async function browseFolder(folderId, page = 1) {
  currentView = 'files';
  currentFolderId = folderId;
  const trashActions = $('trash-actions');
  trashActions.style.display = 'none';
  trashActions.innerHTML = '';
  const path = folderId ? `/storage/folder/content/${folderId}` : '/storage/folder/content';
  const d = await apiCall('GET', `${path}?page=${page}&per_page=20`);
  if (!d) return toast('No autenticado o error', 'error');

  currentItems.folders = d.folders || [];
  currentItems.files = d.files || [];

  if (!folderHierarchy) {
    folderHierarchy = await apiCall('GET', '/storage/folders/hierarchy');
  }

  renderBreadcrumb(folderId);
  renderGrid(d);
  renderPagination(d);
  loadStorageInfo();
}

function browseRoot() { currentView = 'files'; browseFolder(null); }

// ── Trash ──
async function browseTrash() {
  currentView = 'trash';
  currentFolderId = null;
  const d = await apiCall('GET', '/storage/trash');
  if (!d) return toast('No autenticado o error', 'error');
  currentTrashItems = [...(d.folders || []).map(f => ({ ...f, type: 'folder' })), ...(d.files || []).map(f => ({ ...f, type: 'file', name: f.original_name }))];

  const bc = $('breadcrumb');
  bc.innerHTML = '';
  const root = document.createElement('span');
  root.className = 'breadcrumb-item';
  root.innerHTML = ICONS.folder.replace('stroke="#6366f1"', 'stroke="currentColor"') + ' Raíz';
  root.onclick = () => { currentView = 'files'; browseRoot(); };
  bc.appendChild(root);
  const sep = document.createElement('span');
  sep.className = 'breadcrumb-sep'; sep.innerHTML = ICONS.chevronRight;
  bc.appendChild(sep);
  const cur = document.createElement('span');
  cur.className = 'breadcrumb-current';
  cur.textContent = 'Papelera';
  bc.appendChild(cur);

  renderTrash(d);
}

function renderTrash(data) {
  const grid = $('file-grid');
  grid.innerHTML = '';
  const folders = data.folders || [];
  const files = data.files || [];

  if (folders.length === 0 && files.length === 0) {
    grid.innerHTML = '<div class="empty-msg">La papelera está vacía</div>';
    $('pagination').innerHTML = '';
    const ta = $('trash-actions');
    ta.style.display = 'none';
    ta.innerHTML = '';
    $('storage-info-bar').style.display = 'none';
    return;
  }

  // Empty trash button/count lives in #trash-actions — never overwrite #storage-info-bar's children
  $('storage-info-bar').style.display = 'block';
  $('trash-actions').style.display = 'block';
  $('trash-actions').innerHTML = `
    <div style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:0.7rem;color:var(--text-dim)">${folders.length + files.length} elemento(s) en papelera</span>
      <button onclick="emptyTrash()" style="background:var(--danger);color:#fff;border:none;padding:0.25rem 0.6rem;border-radius:var(--radius);font-size:0.68rem;cursor:pointer;font-family:inherit">Vaciar papelera</button>
    </div>`;

  folders.forEach(f => {
    const el = document.createElement('div');
    el.className = 'file-item trashed';
    el.innerHTML = `
      <div class="icon">${ICONS.folder}</div>
      <div class="name">${esc(f.name)}</div>
      <div class="meta">Eliminada: ${fmtDate(f.deleted_at)}</div>
      <button class="more-btn" onclick="event.stopPropagation();showContextMenu(event,'folder','${f.id}','${esc(f.name)}',true)">${ICONS.gear}</button>`;
    el.onclick = () => showContextMenuOnItem(el, 'folder', f.id, f.name, true);
    grid.appendChild(el);
  });

  files.forEach(f => {
    const ext = (f.extension || '').replace('.', '');
    const el = document.createElement('div');
    el.className = 'file-item trashed';
    el.innerHTML = `
      <div class="icon">${getFileIcon(ext, f.mime_type)}</div>
      <div class="name">${esc(f.original_name)}</div>
      <div class="meta">${fmtSize(f.size)} · ${fmtDate(f.deleted_at)}</div>
      <button class="more-btn" onclick="event.stopPropagation();showContextMenu(event,'file','${f.id}','${esc(f.original_name)}',true)">${ICONS.gear}</button>`;
    el.onclick = () => showContextMenuOnItem(el, 'file', f.id, f.original_name, true);
    grid.appendChild(el);
  });

  $('pagination').innerHTML = '';
}

async function restoreItem(type, id) {
  if (type === 'file') {
    const item = currentTrashItems.find(i => i.id == id && i.type === type);
    if (item) {
      const params = new URLSearchParams({ name: item.name });
      if (item.folder_id) params.set('folder_id', item.folder_id);
      const check = await apiCall('GET', `/storage/file/check-name?${params}`);
      if (check && check.exists) {
        pendingRestoreConflict = { type, id, name: item.name, conflicting_file_id: check.file_id };
        $('restore-conflict-modal-body').innerHTML =
          `Ya existe un archivo llamado <span class="conflict-name">${esc(item.name)}</span> en la carpeta destino.<br><br>¿Reemplazar el archivo existente?`;
        $('restore-conflict-modal').classList.remove('hidden');
        return;
      }
    }
  }
  const endpoint = type === 'folder' ? `/storage/folder/${id}/restore` : `/storage/file/${id}/restore`;
  const d = await apiCall('POST', endpoint);
  if (d) { toast('Restaurado'); browseTrash(); loadStorageInfo(); }
}

function closeRestoreConflictModal() {
  $('restore-conflict-modal').classList.add('hidden');
  pendingRestoreConflict = null;
}

async function confirmRestoreReplace() {
  if (!pendingRestoreConflict) return;
  const { type, id } = pendingRestoreConflict;
  closeRestoreConflictModal();
  const endpoint = type === 'folder' ? `/storage/folder/${id}/restore` : `/storage/file/${id}/restore`;
  const d = await apiCall('POST', endpoint, { overwrite: true });
  if (d) { toast('Restaurado y reemplazado'); browseTrash(); loadStorageInfo(); }
}

async function permanentDeleteItem(type, id) {
  const result = await Swal.fire({
    title: `¿Eliminar ${type} permanentemente?`, text: 'Esta acción no se puede deshacer.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (!result.isConfirmed) return;
  const d = await apiCall('DELETE', `/storage/trash/${id}/permanent`, { type });
  if (d) { toast('Eliminado permanentemente'); browseTrash(); loadStorageInfo(); }
}

async function emptyTrash() {
  const result = await Swal.fire({
    title: '¿Vaciar toda la papelera?', text: 'Esta acción no se puede deshacer.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Vaciar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (!result.isConfirmed) return;
  const d = await apiCall('DELETE', '/storage/trash');
  if (d !== null) { toast('Papelera vaciada'); browseTrash(); loadStorageInfo(); }
}

// ── Breadcrumb ──
function renderBreadcrumb(folderId) {
  const bc = $('breadcrumb');
  bc.innerHTML = '';
  const root = document.createElement('span');
  root.className = 'breadcrumb-item';
  root.innerHTML = ICONS.folder.replace('stroke="#6366f1"', 'stroke="currentColor"') + ' Raíz';
  root.onclick = () => browseFolder(null);
  bc.appendChild(root);

  if (!folderId) return;

  const path = findFolderPath(folderId, folderHierarchy || []);
  path.forEach(f => {
    const sep = document.createElement('span');
    sep.className = 'breadcrumb-sep'; sep.textContent = '/';
    bc.appendChild(sep);
    const span = document.createElement('span');
    span.className = 'breadcrumb-item';
    span.textContent = f.name;
    span.onclick = () => browseFolder(f.id);
    bc.appendChild(span);
  });

  const last = path[path.length - 1];
  if (last) {
    const lastSpan = bc.querySelector('.breadcrumb-item:last-child');
    lastSpan.className = 'breadcrumb-current';
    lastSpan.onclick = null;
  }
}

function findFolderPath(targetId, nodes) {
  for (const node of nodes) {
    if (node.id === targetId) return [node];
    if (node.children && node.children.length) {
      const found = findFolderPath(targetId, node.children);
      if (found) return [node, ...found];
    }
  }
  return null;
}

// ── Grid ──
function renderGrid(data) {
  const grid = $('file-grid');
  grid.innerHTML = '';
  const folders = currentItems.folders;
  const files = currentItems.files;

  if (folders.length === 0 && files.length === 0) {
    grid.innerHTML = '<div class="empty-msg">Carpeta vacía</div>';
    return;
  }

  folders.forEach(f => {
    const el = document.createElement('div');
    el.className = 'file-item';
    el.innerHTML = `
      <div class="icon">${ICONS.folder}</div>
      <div class="name">${esc(f.name)}</div>
      <div class="meta">${fmtDate(f.created_at)}</div>
      <button class="more-btn" onclick="event.stopPropagation();showContextMenu(event,'folder','${f.id}','${esc(f.name)}',false)">${ICONS.gear}</button>`;
    el.ondblclick = () => browseFolder(f.id);
    grid.appendChild(el);
  });

  files.forEach(f => {
    const ext = (f.extension || '').replace('.', '');
    const el = document.createElement('div');
    el.className = 'file-item';
    el.innerHTML = `
      <div class="icon">${getFileIcon(ext, f.mime_type)}</div>
      <div class="name">${esc(f.original_name)}</div>
      <div class="meta">${fmtSize(f.size)} · ${ext || '?'}</div>
      <button class="more-btn" onclick="event.stopPropagation();showContextMenu(event,'file','${f.id}','${esc(f.original_name)}',false)">${ICONS.gear}</button>`;
    el.onclick = () => showDetail('file', f.id);
    grid.appendChild(el);
  });
}

function renderPagination(data) {
  const pg = $('pagination');
  pg.innerHTML = '';
  const fp = data.pagination?.files;
  if (!fp || fp.last_page <= 1) return;

  const prev = document.createElement('button');
  prev.innerHTML = ICONS.arrowLeft + ' Ant';
  prev.disabled = fp.current_page <= 1;
  prev.onclick = () => browseFolder(currentFolderId, fp.current_page - 1);
  pg.appendChild(prev);

  const info = document.createElement('span');
  info.className = 'page-info';
  info.textContent = `${fp.current_page}/${fp.last_page}`;
  pg.appendChild(info);

  const next = document.createElement('button');
  next.innerHTML = 'Sig ' + ICONS.arrowRight;
  next.disabled = fp.current_page >= fp.last_page;
  next.onclick = () => browseFolder(currentFolderId, fp.current_page + 1);
  pg.appendChild(next);
}

// ── Context Menu ──
function showContextMenu(e, type, id, name, isTrash) {
  e.preventDefault(); e.stopPropagation();
  const menu = $('context-menu');
  let items = '';
  if (isTrash) {
    items = `
      <div class="context-menu-item" onclick="hideContextMenu();restoreItem('${type}','${id}')">${ICONS.restore} Restaurar</div>
      <div class="context-menu-sep"></div>
      <div class="context-menu-item danger" onclick="hideContextMenu();permanentDeleteItem('${type}','${id}')">${ICONS.permDelete} Eliminar permanentemente</div>`;
  } else if (type === 'folder') {
    items = `
      <div class="context-menu-item" onclick="hideContextMenu();browseFolder('${id}')">${ICONS.folder} Abrir</div>
      <div class="context-menu-item" onclick="hideContextMenu();showDetail('folder','${id}')">${ICONS.info} Propiedades</div>
      <div class="context-menu-sep"></div>
      <div class="context-menu-item" onclick="hideContextMenu();promptRename('folder','${id}','${esc(name)}')">${ICONS.edit} Renombrar</div>
      <div class="context-menu-item" onclick="hideContextMenu();openMoveModal('folder','${id}','${esc(name)}')">${ICONS.move} Mover</div>
      <div class="context-menu-sep"></div>
      <div class="context-menu-item danger" onclick="hideContextMenu();deleteItem('folder','${id}')">${ICONS.trash} Eliminar</div>`;
  } else {
    items = `
      <div class="context-menu-item" onclick="hideContextMenu();downloadFileBrowser('${id}')">${ICONS.download} Descargar</div>
      <div class="context-menu-item" onclick="hideContextMenu();openShareModal('${id}','${esc(name)}')">${ICONS.share} Compartir</div>
      <div class="context-menu-item" onclick="hideContextMenu();promptReplaceFile('${id}')">${ICONS.upload} Reemplazar archivo</div>
      <div class="context-menu-item" onclick="hideContextMenu();showDetail('file','${id}')">${ICONS.info} Propiedades</div>
      <div class="context-menu-sep"></div>
      <div class="context-menu-item" onclick="hideContextMenu();promptRename('file','${id}','${esc(name)}')">${ICONS.edit} Renombrar</div>
      <div class="context-menu-item" onclick="hideContextMenu();openMoveModal('file','${id}','${esc(name)}')">${ICONS.move} Mover</div>
      <div class="context-menu-item" onclick="hideContextMenu();showVersions('${id}')">${ICONS.versions} Versiones</div>
      <div class="context-menu-item" onclick="hideContextMenu();showActivity('${id}')">${ICONS.activity} Actividad</div>
      <div class="context-menu-sep"></div>
      <div class="context-menu-item danger" onclick="hideContextMenu();deleteItem('file','${id}')">${ICONS.trash} Eliminar</div>`;
  }
  menu.innerHTML = items;
  menu.classList.add('show');
  positionMenu(menu, e.clientX, e.clientY);
}

function showContextMenuOnItem(el, type, id, name, isTrash) {
  const rect = el.getBoundingClientRect();
  showContextMenu({ preventDefault(){}, stopPropagation(){}, clientX: rect.right - 10, clientY: rect.top + 10 }, type, id, name, isTrash);
}

function positionMenu(menu, x, y) {
  menu.style.left = '0'; menu.style.top = '0';
  const rect = menu.getBoundingClientRect();
  if (x + rect.width > window.innerWidth) x = window.innerWidth - rect.width - 8;
  if (y + rect.height > window.innerHeight) y = window.innerHeight - rect.height - 8;
  menu.style.left = x + 'px';
  menu.style.top = y + 'px';
}

function hideContextMenu() { $('context-menu').classList.remove('show'); }
document.addEventListener('click', hideContextMenu);
document.addEventListener('contextmenu', e => {
  if (!e.target.closest('.file-item')) hideContextMenu();
});

// ── Detail Panel / Properties ──
async function showDetail(type, id) {
  const d = type === 'folder'
    ? await apiCall('GET', `/storage/folder/${id}`)
    : await apiCall('GET', `/storage/file/${id}`);
  if (!d) return;
  propsData = d;
  propsType = type;
  renderProps('general');
  $('props-overlay').classList.remove('hidden');
}

function renderProps(tab) {
  const body = $('props-body');
  const actions = $('props-actions');
  const d = propsData;
  const type = propsType;
  const isFile = type === 'file';
  const ext = isFile ? (d.extension || '').replace('.', '') : '';
  const icon = isFile ? getFileIcon(ext, d.mime_type) : ICONS.folder;

  $('props-title').innerHTML = icon + ' Propiedades';

  if (tab === 'general') {
    let html = '';
    if (isFile) {
      html += propsRow('Nombre', d.original_name);
      html += propsRow('Tipo', d.mime_type || '—');
      html += propsRow('Extensión', ext || '—');
      html += propsRow('Tamaño', fmtSize(d.size));
      html += propsRow('Ubicación', d.folder_id || 'Raíz');
    } else {
      html += propsRow('Nombre', d.name);
      html += propsRow('Tipo', 'Carpeta');
      html += propsRow('Ubicación', d.parent_id || 'Raíz');
    }
    body.innerHTML = html;
  } else {
    let html = '';
    html += propsRow('ID', d.id);
    html += propsRow('Creado', fmtDate(d.created_at));
    html += propsRow('Actualizado', fmtDate(d.updated_at));
    if (isFile && d.deleted_at) html += propsRow('Eliminado', fmtDate(d.deleted_at));
    body.innerHTML = html;
  }

  let acts = '';
  if (isFile) {
    acts += `<button style="background:var(--accent);color:#fff" onclick="closeProps();downloadFileBrowser('${d.id}')">${ICONS.download} Descargar</button>`;
    acts += `<button style="background:transparent;color:var(--text);border:1px solid var(--border)" onclick="closeProps();openShareModal('${d.id}','${esc(d.original_name)}')">${ICONS.share} Compartir</button>`;
    acts += `<button style="background:transparent;color:var(--text);border:1px solid var(--border)" onclick="closeProps();promptReplaceFile('${d.id}')">${ICONS.upload} Reemplazar</button>`;
    acts += `<button style="background:transparent;color:var(--text);border:1px solid var(--border)" onclick="closeProps();promptRename('file','${d.id}','${esc(d.original_name)}')">${ICONS.edit} Renombrar</button>`;
    acts += `<button style="background:var(--danger);color:#fff" onclick="closeProps();deleteItem('file','${d.id}')">${ICONS.trash} Eliminar</button>`;
  } else {
    acts += `<button style="background:var(--accent);color:#fff" onclick="closeProps();browseFolder('${d.id}')">${ICONS.folder} Abrir</button>`;
    acts += `<button style="background:transparent;color:var(--text);border:1px solid var(--border)" onclick="closeProps();promptRename('folder','${d.id}','${esc(d.name)}')">${ICONS.edit} Renombrar</button>`;
    acts += `<button style="background:var(--danger);color:#fff" onclick="closeProps();deleteItem('folder','${d.id}')">${ICONS.trash} Eliminar</button>`;
  }
  actions.innerHTML = acts;
}

function propsRow(label, value) {
  return `<div class="props-row"><span class="props-label">${label}</span><span class="props-value">${esc(String(value ?? '—'))}</span></div>`;
}

function switchPropsTab(tab, el) {
  document.querySelectorAll('.props-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  renderProps(tab);
}

function closeProps() { $('props-overlay').classList.add('hidden'); }

// ── Move ──
async function openMoveModal(type, id, name) {
  moveTarget = { type, id, name };
  moveSelectedFolder = '';
  $('move-title').innerHTML = ICONS.move + ` Mover "${name}"`;
  const body = $('move-body');
  body.innerHTML = '<div class="spinner" style="display:block;margin:1rem auto"></div>';
  $('move-overlay').classList.remove('hidden');

  const d = await apiCall('GET', '/storage/folders/hierarchy');
  if (!d) { body.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:1rem">Error cargando carpetas</div>'; return; }
  folderHierarchy = d;

  let html = `<div class="move-folder selected" data-id="" onclick="selectMoveFolder(this,'')">${ICONS.folder} Raíz</div>`;
  const renderTree = (folders, depth) => {
    folders.forEach(f => {
      if (type === 'folder' && f.id === id) return;
      html += `<div class="move-folder" data-id="${f.id}" onclick="selectMoveFolder(this,'${f.id}')" style="padding-left:${0.5 + depth * 1.2}rem">${ICONS.folder} ${esc(f.name)}</div>`;
      if (f.children && f.children.length) renderTree(f.children, depth + 1);
    });
  };
  renderTree(d, 0);
  body.innerHTML = html;
}

function selectMoveFolder(el, id) {
  document.querySelectorAll('.move-folder').forEach(f => f.classList.remove('selected'));
  el.classList.add('selected');
  moveSelectedFolder = id;
}

async function confirmMove() {
  if (!moveTarget) return;
  const { type, id, name } = moveTarget;

  if (type === 'file') {
    const params = new URLSearchParams({ name: name });
    if (moveSelectedFolder) params.set('folder_id', moveSelectedFolder);

    const check = await apiCall('GET', `/storage/file/check-name?${params}`);
    if (check && check.exists) {
      pendingMoveConflict = {
        type, id, name,
        destination_folder_id: moveSelectedFolder || null,
        conflicting_file_id: check.file_id
      };
      closeMoveModal();
      $('move-conflict-modal-body').innerHTML =
        `Ya existe un archivo llamado <span class="conflict-name">${esc(name)}</span> en la carpeta destino.<br><br>` +
        `¿Reemplazar el archivo existente?`;
      $('move-conflict-modal').classList.remove('hidden');
      return;
    }
  } else if (type === 'folder') {
    const params = new URLSearchParams({ name: name });
    if (moveSelectedFolder) params.set('parent_id', moveSelectedFolder);

    const check = await apiCall('GET', `/storage/folder/check-name?${params}`);
    if (check && check.exists) {
      pendingMoveConflict = {
        type, id, name,
        destination_folder_id: moveSelectedFolder || null,
        conflicting_folder_id: check.conflicting_folder?.id
      };
      closeMoveModal();
      $('move-conflict-modal-body').innerHTML =
        `Ya existe una carpeta llamada <span class="conflict-name">${esc(name)}</span> en la carpeta destino.<br><br>` +
        `¿Reemplazar la carpeta existente? (Se fusionarán los contenidos)`;
      $('move-conflict-modal').classList.remove('hidden');
      return;
    }
  }

  const endpoint = type === 'folder' ? `/storage/folder/${id}/move` : `/storage/file/${id}/move`;
  const body = { destination_folder_id: moveSelectedFolder || null };
  const d = await apiCall('PATCH', endpoint, body);
  closeMoveModal();
  if (d) { toast('Movido'); folderHierarchy = null; browseFolder(currentFolderId); }
}

function closeMoveConflictModal() {
  $('move-conflict-modal').classList.add('hidden');
  pendingMoveConflict = null;
}

async function confirmMoveReplace() {
  if (!pendingMoveConflict) return;
  const { type, id, destination_folder_id } = pendingMoveConflict;
  closeMoveConflictModal();

  const endpoint = type === 'folder' ? `/storage/folder/${id}/move` : `/storage/file/${id}/move`;
  const body = { destination_folder_id: destination_folder_id || null, overwrite: true };
  const d = await apiCall('PATCH', endpoint, body);
  closeMoveModal();
  if (d) { toast('Movido y reemplazado'); folderHierarchy = null; browseFolder(currentFolderId); }
}

function closeMoveModal() { $('move-overlay').classList.add('hidden'); }

// ── Versions / Activity ──
async function showVersions(id) {
  const [d, check] = await Promise.all([
    apiCall('GET', `/storage/file/${id}/versions`),
    apiCall('GET', `/storage/file/${id}/versions/check`)
  ]);
  if (!d) return;
  const versions = Array.isArray(d) ? d : [];
  let html = versions.length === 0 ? '<div style="color:var(--text-muted)">No hay versiones</div>' :
    versions.map((v, i) => `<div style="padding:0.3rem 0;font-size:0.78rem;border-bottom:1px solid var(--border)">v${i+1} — ${fmtDate(v.created_at)} — ${fmtSize(v.size)}</div>`).join('');
  let actions = '';
  if (check?.has_older) actions += `<button class="swal-action-btn" onclick="restoreVersion('${id}','back')">Restaurar versión anterior</button>`;
  if (check?.has_newer) actions += `<button class="swal-action-btn" onclick="restoreVersion('${id}','front')">Rehacer a versión nueva</button>`;
  Swal.fire({ title: 'Versiones', html: `<div style="text-align:left;max-height:260px;overflow-y:auto">${html}</div>${actions}`, background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
}

async function restoreVersion(id, dir) {
  const d = await apiCall('POST', `/storage/file/${id}/versions/restore-${dir}`);
  if (!d) return toast(dir === 'back' ? 'No hay versión anterior disponible' : 'No hay versión posterior disponible', 'error');
  Swal.close();
  toast('Versión restaurada');
  showVersions(id);
}

async function showActivity(id) {
  const d = await apiCall('GET', `/storage/file/${id}/activity`);
  if (!d) return;
  const acts = Array.isArray(d) ? d : [];
  let html = acts.length === 0 ? '<div style="color:var(--text-muted)">Sin actividad</div>' :
    acts.map(a => `<div style="padding:0.3rem 0;font-size:0.78rem;border-bottom:1px solid var(--border)">${fmtDate(a.created_at)} — ${esc(a.action || a.type || JSON.stringify(a))}</div>`).join('');
  const actions = `<div style="display:flex;gap:0.35rem;margin-top:0.6rem">
    <button class="swal-action-btn secondary flex" onclick="restoreActivity('${id}','back')">Deshacer</button>
    <button class="swal-action-btn secondary flex" onclick="restoreActivity('${id}','front')">Rehacer</button>
  </div>`;
  Swal.fire({ title: 'Actividad', html: `<div style="text-align:left;max-height:260px;overflow-y:auto">${html}</div>${actions}`, background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
}

async function restoreActivity(id, dir) {
  const d = await apiCall('POST', `/storage/file/${id}/activity/restore-${dir}`);
  if (!d) return toast(dir === 'back' ? 'No hay acción para deshacer' : 'No hay acción para rehacer', 'error');
  Swal.close();
  toast('Acción restaurada');
  showActivity(id);
}

// ── Browser Actions ──
async function createFolderBrowser() {
  const name = $('new-folder-name').value.trim();
  if (!name) return toast('Ingrese un nombre', 'warning');
  if (!isValidName(name).valid) return showInvalidNameError(name);
  const body = { name };
  if (currentFolderId) body.parent_id = currentFolderId;
  const d = await apiCall('POST', '/storage/folder', body);
  if (d) { toast('Carpeta creada'); $('new-folder-name').value = ''; folderHierarchy = null; browseFolder(currentFolderId); }
}

let pendingUploadFile = null;
let pendingUploadMode = 'chunks';
let pendingReplaceFileId = null;
let pendingMoveConflict = null;
let pendingRestoreConflict = null;
let currentTrashItems = [];
let uploadMenuOpen = false;

function toggleUploadMenu() {
  const menu = $('upload-menu');
  uploadMenuOpen = !uploadMenuOpen;
  menu.style.display = uploadMenuOpen ? 'block' : 'none';
}

function selectUploadMode(mode) {
  pendingUploadMode = mode;
  uploadMenuOpen = false;
  $('upload-menu').style.display = 'none';
  $('upload-file-input').click();
}

document.addEventListener('click', e => {
  if (!e.target.closest('.upload-btn-group')) {
    const menu = $('upload-menu');
    if (menu) { menu.style.display = 'none'; uploadMenuOpen = false; }
  }
});

function closeUploadModal() {
  $('upload-modal').classList.add('hidden');
  pendingUploadFile = null;
  pendingReplaceFileId = null;
}

async function confirmUpload() {
  if (!pendingUploadFile) return;
  const file = pendingUploadFile;
  const mode = pendingUploadMode;
  closeUploadModal();
  try {
    if (mode === 'chunks') {
      await ChunkedUpload.upload(file, currentFolderId);
    } else {
      await UploadProgress.upload(file, currentFolderId, true);
    }
    toast('Archivo subido'); loadStorageInfo(); browseFolder(currentFolderId);
  } catch (e) {
    if (e.message !== 'Cancelado') toast('Error: ' + e.message, 'error');
  }
}

async function confirmReplaceUpload() {
  if (!pendingUploadFile || !pendingReplaceFileId) return;
  const file = pendingUploadFile;
  const fileId = pendingReplaceFileId;
  closeUploadModal();
  try {
    const fd = new FormData();
    fd.append('file', file);
    const d = await apiCall('PUT', `/storage/file/${fileId}`, fd, true);
    if (d) {
      toast('Archivo reemplazado');
      loadStorageInfo();
      browseFolder(currentFolderId);
    }
  } catch (e) {
    if (e.message !== 'Cancelado') toast('Error: ' + e.message, 'error');
  }
}

async function uploadFileBrowser() {
  const file = $('upload-file-input').files[0];
  if (!file) return toast('Seleccione un archivo', 'warning');
  const mode = pendingUploadMode;

  const params = new URLSearchParams({ name: file.name });
  if (currentFolderId) params.set('folder_id', currentFolderId);

  const check = await apiCall('GET', `/storage/file/check-name?${params}`);
  if (!check) return;

  if (check.exists) {
    pendingUploadFile = file;
    pendingReplaceFileId = check.file_id;
    $('upload-modal-body').innerHTML =
      `Ya existe un archivo llamado <span class="conflict-name">${esc(file.name)}</span> en esta ubicación.<br><br>` +
      `Se subirá como: <span class="suggested-name">${esc(check.suggested_name)}</span>`;
    $('upload-modal').classList.remove('hidden');
  } else {
    try {
      if (mode === 'chunks') {
        await ChunkedUpload.upload(file, currentFolderId);
      } else {
        await UploadProgress.upload(file, currentFolderId, true);
      }
      toast('Archivo subido'); loadStorageInfo(); browseFolder(currentFolderId);
    } catch (e) {
      if (e.message !== 'Cancelado') toast('Error: ' + e.message, 'error');
    }
  }
  $('upload-file-input').value = '';
}

async function downloadFileBrowser(id) {
  const d = await apiCall('GET', `/storage/file/${id}/download`);
  if (d?.url) window.open(d.url, '_blank');
}

async function deleteItem(type, id) {
  const result = await Swal.fire({
    title: `¿Eliminar ${type}?`, icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (!result.isConfirmed) return;
  const d = type === 'folder'
    ? await apiCall('DELETE', `/storage/folder/${id}`)
    : await apiCall('DELETE', `/storage/file/${id}`);
  if (d) { toast('Eliminado'); loadStorageInfo(); folderHierarchy = null; browseFolder(currentFolderId); }
}

async function promptRename(type, id, currentName) {
  // ponytail: for files, show only the name part (no extension) in the input
  const displayName = type === 'file' ? currentName.replace(/\.[^.]+$/, '') : currentName;
  const { value: newName } = await Swal.fire({
    title: 'Nuevo nombre', input: 'text', inputValue: displayName,
    showCancelButton: true, confirmButtonText: 'Renombrar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1',
    inputValidator: v => {
      if (!v || v === displayName) return 'Ingresa un nombre diferente';
      const { valid, invalidChars } = isValidName(v);
      if (!valid) return `Caracteres no permitidos: ${invalidChars.join(' ')}`;
      return null;
    }
  });
  if (!newName) return;
  // ponytail: strip any extension the user might have typed — backend enforces, this is UX
  const safeName = type === 'file' ? newName.replace(/\.[^.]+$/, '') : newName;
  const endpoint = type === 'folder' ? `/storage/folder/${id}/rename` : `/storage/file/${id}/rename`;
  const d = await apiCall('PATCH', endpoint, { name: safeName });
  if (d) { toast('Renombrado'); folderHierarchy = null; browseFolder(currentFolderId); }
}

// ── Share Link ──
let shareFileId = null;

function openShareModal(fileId, fileName) {
  shareFileId = fileId;
  $('share-title').innerHTML = ICONS.share + ' Compartir "' + fileName + '"';
  $('share-expires').value = '';
  $('share-max-downloads').value = '';
  $('share-password').value = '';
  $('share-form').style.display = '';
  $('share-result').style.display = 'none';
  $('share-generate-btn').style.display = '';
  $('share-overlay').classList.remove('hidden');
}

function closeShareModal() {
  $('share-overlay').classList.add('hidden');
  shareFileId = null;
}

async function generateShareLink() {
  if (!shareFileId) return;
  const body = {};
  const expires = $('share-expires').value;
  if (expires) body.expires_at = expires.replace('T', 'T') + ':00';
  const maxDl = $('share-max-downloads').value;
  if (maxDl) body.max_downloads = parseInt(maxDl);
  const pass = $('share-password').value;
  if (pass) body.password = pass;

  const d = await apiCall('POST', `/share/file/${shareFileId}`, body);
  if (!d?.shareLink) return;

  const link = d.shareLink;
  $('share-link-url').value = link.url;
  let meta = '';
  if (link.expires_at) meta += `Expira: ${fmtDate(link.expires_at)}`;
  if (link.max_downloads) meta += `${meta ? ' · ' : ''}Max descargas: ${link.max_downloads}`;
  if (link.requires_password) meta += `${meta ? ' · ' : ''}Protegido con contraseña`;
  $('share-meta').textContent = meta;
  $('share-form').style.display = 'none';
  $('share-result').style.display = '';
  $('share-generate-btn').style.display = 'none';
}

function copyShareLink() {
  const url = $('share-link-url').value;
  navigator.clipboard.writeText(url).then(() => toast('Link copiado'));
}

// ═══════════════════════════════════════════
//  PERFIL
// ═══════════════════════════════════════════

async function openProfileModal() {
  $('profile-name').value = '';
  $('profile-last-name').value = '';
  $('profile-pass').value = '';
  $('profile-new-pass').value = '';
  const me = await apiCall('GET', '/me');
  if (me?.name) $('profile-name').value = me.name;
  if (me?.last_name) $('profile-last-name').value = me.last_name;
  $('profile-modal').classList.remove('hidden');
}

function closeProfileModal() { $('profile-modal').classList.add('hidden'); }

async function updateProfile() {
  const name = $('profile-name').value.trim();
  if (!name) return toast('El nombre es obligatorio', 'warning');
  const d = await apiCall('PUT', '/user', { name, last_name: $('profile-last-name').value.trim() });
  if (d) { toast('Perfil actualizado'); closeProfileModal(); updateStatus(); }
}

async function updatePassword() {
  const password = $('profile-pass').value;
  const newPassword = $('profile-new-pass').value;
  if (!password || !newPassword) return toast('Completá ambos campos', 'warning');
  if (newPassword.length < 8) return toast('La nueva contraseña debe tener al menos 8 caracteres', 'warning');
  const d = await apiCall('PATCH', '/user', { password, newPassword });
  if (d === false) return toast('Contraseña actual incorrecta', 'error');
  if (d) { toast('Contraseña cambiada'); closeProfileModal(); }
}

async function promptDeleteAccount() {
  const r1 = await Swal.fire({
    title: '¿Eliminar tu cuenta?',
    html: 'Se borrarán tu cuenta y <strong>todos tus archivos</strong> de forma permanente.<br>Esta acción no se puede deshacer.',
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Continuar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (!r1.isConfirmed) return;
  const { value } = await Swal.fire({
    title: 'Confirmación final',
    input: 'text', inputPlaceholder: 'Escribí ELIMINAR',
    inputValidator: v => v === 'ELIMINAR' ? null : 'Debés escribir exactamente "ELIMINAR"',
    showCancelButton: true, confirmButtonText: 'Eliminar cuenta', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#ef4444', cancelButtonColor: '#6366f1'
  });
  if (value !== 'ELIMINAR') return;
  const d = await apiCall('DELETE', '/user');
  if (d) {
    toast('Cuenta eliminada');
    closeProfileModal();
    currentView = 'files'; currentFolderId = null;
    $('file-grid').innerHTML = ''; $('pagination').innerHTML = '';
    $('storage-info-bar').style.display = 'none';
    updateStatus();
  }
}

// ═══════════════════════════════════════════
//  EXPANSIONES
// ═══════════════════════════════════════════

const fmtMoney = c => `$${(c / 100).toFixed(2)}`;

async function openExpansionsModal() {
  const list = $('expansions-list');
  $('expansions-modal').classList.remove('hidden');
  list.innerHTML = '<div class="spinner" style="display:block;margin:1rem auto"></div>';
  const d = await apiCall('GET', '/expansions');
  if (!d) { list.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:1rem">No se pudieron cargar las expansiones</div>'; return; }
  const plans = Array.isArray(d) ? d : [];
  if (!plans.length) { list.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:1rem">No hay expansiones disponibles</div>'; return; }
  list.innerHTML = plans.map(p => `
    <div class="expansion-item">
      <div class="expansion-info">
        <div class="expansion-name">${esc(p.name)}</div>
        <div class="expansion-meta">${fmtSize(p.storage_bytes)} · ${fmtMoney(p.price_cents)}</div>
      </div>
      <div class="expansion-actions">
        <button class="expansion-detail" onclick="expansionDetail(${p.id})">Detalle</button>
        <button class="expansion-buy" onclick="buyExpansion(${p.id},'${esc(p.name)}')">Comprar</button>
      </div>
    </div>`).join('');
}

function closeExpansionsModal() { $('expansions-modal').classList.add('hidden'); }

async function expansionDetail(id) {
  closeExpansionsModal();
  const d = await apiCall('GET', `/expansions/${id}`);
  if (!d) return;
  const rows = Object.entries(d || {}).map(([k, v]) => `<div style="padding:0.2rem 0;font-size:0.72rem">${esc(k)}: ${esc(v ?? '—')}</div>`).join('');
  Swal.fire({ title: 'Expansión', html: `<div style="text-align:left">${rows}</div>`, background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
}

async function buyExpansion(id, name) {
  closeExpansionsModal();
  const r = await Swal.fire({
    title: `Comprar "${name}"`, text: '¿Confirmás la compra de este plan?',
    icon: 'question', showCancelButton: true, confirmButtonText: 'Comprar', cancelButtonText: 'Cancelar',
    background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1', cancelButtonColor: '#6366f1'
  });
  if (!r.isConfirmed) return;
  const d = await apiCall('POST', `/expansions/${id}/buy`);
  if (!d) return;
  loadStorageInfo();
  Swal.fire({ title: 'Comprada', html: `Nuevo límite de almacenamiento: <strong>${fmtSize(d.storage_limit)}</strong>`, background: '#1a1a2e', color: '#e2e8f0', confirmButtonColor: '#6366f1' });
}

// ═══════════════════════════════════════════
//  PROBAR LINK COMPARTIDO
// ═══════════════════════════════════════════

function openShareTestModal() {
  $('share-test-link').value = '';
  $('share-test-config').style.display = 'none';
  $('share-test-modal').classList.remove('hidden');
  $('share-test-link').focus();
}

function closeShareTestModal() { $('share-test-modal').classList.add('hidden'); }

function shareInput() {
  const raw = $('share-test-link').value.trim();
  if (!raw) return null;
  let token = raw;
  try {
    const u = new URL(/^https?:\/\//i.test(raw) ? raw : 'https://' + raw);
    token = u.pathname.split('/').filter(Boolean).pop() || '';
  } catch {
    token = raw.split('/').filter(Boolean).pop() || raw;
  }
  const link = /^https?:\/\//i.test(raw) ? raw
    : raw.startsWith('/') ? location.origin + raw
    : location.origin + '/share/' + token;
  return { token, link };
}

async function shareTestConfig() {
  const input = shareInput();
  if (!input) return toast('Pegá un link compartido', 'warning');
  const d = await apiCall('GET', `/share/${input.token}/config`);
  if (!d) return;
  const c = d.config || d;
  $('share-test-config').innerHTML =
    `<div class="config-row"><span class="config-label">Válido</span><span class="config-value">${c.is_valid ? 'Sí' : 'No'}</span></div>` +
    `<div class="config-row"><span class="config-label">Archivo</span><span class="config-value">${esc(c.file_name || '—')}</span></div>` +
    `<div class="config-row"><span class="config-label">Requiere contraseña</span><span class="config-value">${c.requires_password ? 'Sí' : 'No'}</span></div>` +
    `<div class="config-row"><span class="config-label">Expira</span><span class="config-value">${c.expires_at ? new Date(c.expires_at).toLocaleString('es-AR') : 'Sin expiración'}</span></div>` +
    `<div class="config-row"><span class="config-label">Máx. descargas</span><span class="config-value">${c.max_downloads ?? 'Sin límite'}</span></div>` +
    `<div class="config-row"><span class="config-label">Descargas</span><span class="config-value">${c.download_count ?? 0}</span></div>`;
  $('share-test-config').style.display = '';
}

function shareTestOpen() {
  const input = shareInput();
  if (!input) return toast('Pegá un link compartido', 'warning');
  window.open(input.link, '_blank');
}

// ═══════════════════════════════════════════
//  HELPERS DE ALMACENAMIENTO
// ═══════════════════════════════════════════

function showHelperResult(title, rows) {
  const el = $('helper-result');
  el.style.display = '';
  el.innerHTML = `<div style="font-size:0.75rem;font-weight:600;margin-bottom:0.4rem">${esc(title)}</div>` +
    rows.map(([l, v]) => `<div class="config-row"><span class="config-label">${esc(l)}</span><span class="config-value">${esc(String(v ?? '—'))}</span></div>`).join('');
}

// ── Storage Picker (elige archivo/carpeta del propio storage) ──
let pickerMode = 'file';
let pickerStack = [];
let pickerOnPick = null;
let pickerTimer = null;

function openFilePicker(onPick) { openPickerModal('file', onPick); }
function openFolderPicker(onPick) { openPickerModal('folder', onPick); }

function openPickerModal(mode, onPick) {
  pickerMode = mode;
  pickerOnPick = onPick;
  pickerStack = [];
  $('picker-title').textContent = mode === 'file' ? 'Seleccionar archivo' : 'Seleccionar carpeta (ubicación)';
  const nameInput = $('picker-name-input');
  nameInput.style.display = mode === 'folder' ? '' : 'none';
  if (mode === 'folder') nameInput.value = '';
  $('picker-modal').classList.remove('hidden');
  loadPickerFolder(null);
}

function closePickerModal() {
  clearTimeout(pickerTimer);
  $('picker-modal').classList.add('hidden');
  pickerOnPick = null;
}

async function loadPickerFolder(folderId) {
  const list = $('picker-list');
  list.innerHTML = '<div class="spinner" style="display:block;margin:1rem auto"></div>';
  const path = folderId ? `/storage/folder/content/${folderId}` : '/storage/folder/content';
  const d = await apiCall('GET', path);
  if (!d) { list.innerHTML = '<div class="empty-msg">Error cargando contenido</div>'; return; }
  renderPickerList(d);
}

function renderPickerList(d) {
  const folders = d.folders || [];
  const files = d.files || [];
  const folderMode = pickerMode === 'folder';
  let html = '';
  if (folderMode) {
    html += `<div class="picker-item picker-select" onclick="pickerSelect()">${ICONS.folder} Raíz (sin carpeta)<span class="picker-hint">clic: elegir</span></div>`;
  }
  if (pickerStack.length) {
    html += `<div class="picker-item picker-navigable" onclick="pickerBack()">${ICONS.arrowLeft} ..</div>`;
  }
  folders.forEach(f => {
    const actions = folderMode
      ? `onclick="pickerFolderClick('${f.id}')" ondblclick="pickerOpen('${f.id}')"`
      : `onclick="pickerOpen('${f.id}')"`;
    html += `<div class="picker-item picker-navigable${folderMode ? ' picker-select' : ''}" ${actions}>${ICONS.folder} ${esc(f.name)}<span class="picker-hint">${folderMode ? 'clic: elegir · doble clic: abrir' : 'abrir'}</span></div>`;
  });
  files.forEach(f => {
    const icon = getFileIcon((f.extension || '').replace('.', ''), f.mime_type);
    html += folderMode
      ? `<div class="picker-item picker-disabled">${icon} ${esc(f.original_name)}</div>`
      : `<div class="picker-item picker-select" onclick="pickerSelect('${f.id}')">${icon} ${esc(f.original_name)}<span class="picker-hint">clic: elegir</span></div>`;
  });
  $('picker-list').innerHTML = html || '<div class="empty-msg">Carpeta vacía</div>';
}

function pickerOpen(id) {
  clearTimeout(pickerTimer);
  if (id === (pickerStack[pickerStack.length - 1] || null)) return;
  pickerStack.push(id);
  loadPickerFolder(id);
}

function pickerBack() {
  clearTimeout(pickerTimer);
  pickerStack.pop();
  loadPickerFolder(pickerStack[pickerStack.length - 1] || null);
}

function pickerFolderClick(id) {
  clearTimeout(pickerTimer);
  pickerTimer = setTimeout(() => pickerSelect(id), 250);
}

function pickerSelect(id = null) {
  clearTimeout(pickerTimer);
  const cb = pickerOnPick;
  const name = $('picker-name-input').value.trim();
  closePickerModal();
  cb(id, name);
}

function quickVerify() {
  $('verify-size-input').value = 1;
  $('verify-size-unit').value = '1048576';
  $('verify-modal').classList.remove('hidden');
  $('verify-size-input').focus();
  $('verify-size-input').select();
}

function closeVerifyModal() { $('verify-modal').classList.add('hidden'); }

async function quickVerifyRun() {
  const mult = parseInt($('verify-size-unit').value) || 1;
  const bytes = Math.round(parseFloat($('verify-size-input').value) * mult);
  if (!bytes || bytes < 1) return toast('Ingresá un tamaño válido (≥ 1)', 'warning');
  const d = await apiCall('POST', '/storage/verify', { file_size: bytes });
  if (!d) return;
  closeVerifyModal();
  showHelperResult('Verificar espacio', [['Subida permitida', d.allowed ? 'Sí' : 'No']]);
}

async function quickCheckName() {
  openFolderPicker(async (parentId, name) => {
    if (!name) return toast('Ingresá un nombre', 'warning');
    const params = new URLSearchParams({ name });
    if (parentId) params.set('parent_id', parentId);
    const d = await apiCall('GET', `/storage/folder/check-name?${params}`);
    if (!d) return;
    const c = d.conflicting_folder;
    const rows = c
      ? [['El nombre existe', 'Sí'], ['Carpeta en conflicto', `${c.name} (ID ${c.id})`], ['Elementos', c.content_count ?? 0]]
      : [['El nombre existe', d.exists ? 'Sí' : 'No']];
    showHelperResult('Check nombre', rows);
  });
}

async function quickVersionCheck() {
  openFilePicker(async fileId => {
    const d = await apiCall('GET', `/storage/file/${fileId}/versions/check`);
    if (!d) return;
    showHelperResult('Versiones', [
      ['Version actual', d.current_version],
      ['Total de versiones', d.total_versions ?? d.total_version],
      ['Hay anterior', d.has_older ? 'Sí' : 'No'],
      ['Hay posterior', d.has_newer ? 'Sí' : 'No']
    ]);
  });
}

// ── Replace file ──
async function promptReplaceFile(id) {
  const input = document.createElement('input');
  input.type = 'file';
  input.onchange = async () => {
    const f = input.files && input.files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('file', f);
    const d = await apiCall('PUT', `/storage/file/${id}`, fd, true);
    if (d) { toast('Archivo reemplazado'); loadStorageInfo(); browseFolder(currentFolderId); }
  };
  input.click();
}

// ── Sidebar & Explorer Toggle ──
function toggleSidebar() {
  const sb = $('sidebar');
  const collapsed = sb.classList.toggle('collapsed');
  sb.style.width = collapsed ? '0' : '';
  $('btn-toggle-sidebar').classList.toggle('active', !collapsed);
}

function toggleExplorer() {
  const panel = $('panel-explorer');
  const collapsed = panel.classList.toggle('collapsed');
  panel.style.width = collapsed ? '0' : '';
  $('btn-toggle-explorer').classList.toggle('active', !collapsed);
}

// ── Console resize (drag handle) ──
(function() {
  const handle = $('console-resize');
  const console_ = $('console-area');
  let startY, startH;

  handle.addEventListener('mousedown', e => {
    e.preventDefault();
    startY = e.clientY;
    startH = console_.offsetHeight;
    handle.classList.add('dragging');
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });

  function onMove(e) {
    const delta = startY - e.clientY;
    const newH = Math.max(80, Math.min(window.innerHeight * 0.6, startH + delta));
    console_.style.height = newH + 'px';
  }

  function onUp() {
    handle.classList.remove('dragging');
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }
})();

// ── Panel resize (vertical drag handles) ──
(function() {
  const rows = [
    ['explorer-resize', 'panel-explorer', 1, 180, 420],
    ['sidebar-resize', 'sidebar', -1, 200, 720]
  ];
  for (const [handleId, panelId, dir, minW, maxW] of rows) {
    const handle = $(handleId);
    const panel = $(panelId);
    let startX, startW;

    handle.addEventListener('mousedown', e => {
      e.preventDefault();
      startX = e.clientX;
      startW = panel.offsetWidth;
      panel.style.transition = 'none';
      handle.classList.add('dragging');
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });

    function onMove(e) {
      const delta = (e.clientX - startX) * dir;
      const newW = Math.max(minW, Math.min(maxW, startW + delta));
      panel.style.width = newW + 'px';
    }

    function onUp() {
      panel.style.transition = '';
      handle.classList.remove('dragging');
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
    }
  }
})();

// ── Keyboard shortcut: Ctrl+Enter to send ──
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    e.preventDefault();
    sendRequest();
  }
});

// ── Init ──
$('btn-toggle-explorer').classList.add('active');
$('btn-toggle-sidebar').classList.add('active');
updateStatus().then(async () => {
  const b = $('status-badge');
  if (b.classList.contains('connected')) {
    loadStorageInfo();
    browseRoot();
  }
});
