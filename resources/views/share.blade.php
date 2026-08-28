<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SzCloud — Archivo compartido</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #ededec; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
        .card { background: #111; border: 1px solid rgba(255,255,255,0.06); border-radius: 0.75rem; box-shadow: 0 8px 40px rgba(0,0,0,0.6); width: 100%; max-width: 400px; padding: 2rem; text-align: center; }
        .icon { margin-bottom: 1rem; }
        .icon svg { width: 48px; height: 48px; color: #6366f1; }
        .file-name { font-size: 1.1rem; font-weight: 600; color: #fff; margin-bottom: 0.5rem; word-break: break-all; }
        .meta { font-size: 0.78rem; color: #706f6c; margin-bottom: 1.25rem; }
        .meta span { margin: 0 0.35rem; }
        .expired { color: #ef4444; font-weight: 500; }
        label { display: block; text-align: left; font-size: 0.7rem; color: #706f6c; margin-bottom: 0.25rem; }
        input[type="password"] { width: 100%; background: #0a0a0a; border: 1px solid rgba(255,255,255,0.06); color: #ededec; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1rem; }
        input[type="password"]:focus { border-color: rgba(255,255,255,0.12); outline: none; }
        .btn { width: 100%; padding: 0.6rem; border: none; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 500; cursor: pointer; font-family: inherit; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-disabled { background: #333; color: #706f6c; cursor: not-allowed; }
        .error { color: #ef4444; font-size: 0.78rem; margin-bottom: 1rem; display: none; }
        .loading { color: #706f6c; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="card" id="card">
        <div class="loading">Cargando...</div>
    </div>

    <script>
        const token = '{{ $token }}';
        const card = document.getElementById('card');

        async function loadShare() {
            try {
                const res = await fetch('/api/share/' + token + '/config');
                if (!res.ok) {
                    card.innerHTML = '<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="file-name" style="color:#ef4444">Link no encontrado</div><div class="meta">Este enlace no es válido o ha expirado.</div>';
                    return;
                }
                const data = await res.json();
                const cfg = data.config;

                if (!cfg.is_valid) {
                    card.innerHTML = '<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div class="file-name expired">Link expirado</div><div class="meta">Este enlace ya no es válido.</div>';
                    return;
                }

                let metaHtml = '';
                if (cfg.file_name) metaHtml += '<span>' + esc(cfg.file_name) + '</span>';
                if (cfg.expires_at) metaHtml += '<span>·</span><span>Expira: ' + fmtDate(cfg.expires_at) + '</span>';
                if (cfg.max_downloads) metaHtml += '<span>·</span><span>' + cfg.download_count + '/' + cfg.max_downloads + ' descargas</span>';
                if (cfg.requires_password) metaHtml += '<span>·</span><span>Protegido</span>';

                let passHtml = '';
                if (cfg.requires_password) {
                    passHtml = '<div style="text-align:left;margin-bottom:1rem"><label>Contraseña</label><input id="pass" type="password" placeholder="Ingresa la contraseña"></div>';
                }

                card.innerHTML =
                    '<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>' +
                    '<div class="file-name">' + esc(cfg.file_name || 'Archivo compartido') + '</div>' +
                    '<div class="meta">' + metaHtml + '</div>' +
                    '<div id="error" class="error"></div>' +
                    passHtml +
                    '<button class="btn btn-primary" onclick="download()">Descargar</button>';
            } catch {
                card.innerHTML = '<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="file-name" style="color:#ef4444">Error de conexión</div>';
            }
        }

        async function download() {
            const passInput = document.getElementById('pass');
            const body = {};
            if (passInput) body.password = passInput.value;

            try {
                const res = await fetch('/api/share/' + token, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (res.ok && data.url) {
                    window.location.href = data.url;
                } else {
                    const err = document.getElementById('error');
                    err.textContent = data.message || 'Error al descargar';
                    err.style.display = 'block';
                }
            } catch {
                const err = document.getElementById('error');
                err.textContent = 'Error de conexión';
                err.style.display = 'block';
            }
        }

        function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function fmtDate(s) { if (!s) return ''; try { return new Date(s).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' }); } catch { return s; } }

        loadShare();
    </script>
</body>
</html>
