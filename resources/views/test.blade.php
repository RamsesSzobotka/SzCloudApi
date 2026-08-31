<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SzCloud API Tester</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|jetbrains-mono:400,500" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="{{ asset('test.css') }}">
</head>
<body>

<!-- ═══ HEADER ═══ -->
<div class="header">
  <div class="header-left">
    <h1>
      <a href="/" class="header-logo">SzCloud</a>
      <span class="header-sub">API Tester</span>
    </h1>
    <span id="status-badge" class="status disconnected"><span class="status-dot"></span>sin token</span>
  </div>
  <div class="header-actions">
    <button class="header-btn" id="btn-toggle-explorer" onclick="toggleExplorer()" title="Mostrar/ocultar archivos">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      Archivos
    </button>
    <button class="header-btn" id="btn-toggle-sidebar" onclick="toggleSidebar()" title="Mostrar/ocultar respuesta">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      Response
    </button>
    <button class="header-btn" id="btn-login" onclick="openLoginModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Login
    </button>
    <button class="header-btn danger-btn" id="btn-logout" onclick="doLogout()" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </button>
  </div>
</div>

<!-- ═══ MAIN LAYOUT: 3 columns ═══ -->
<div class="layout">

  <!-- ═══ LEFT: FILE EXPLORER ═══ -->
  <div class="panel-explorer" id="panel-explorer">
    <div class="panel-explorer-header">
      <span class="panel-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        Archivos
      </span>
      <button class="icon-btn" onclick="toggleExplorer()" title="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="explorer-toolbar">
      <input id="new-folder-name" type="text" placeholder="Nueva carpeta...">
      <button class="icon-btn" onclick="createFolderBrowser()" title="Crear carpeta">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <label class="icon-btn" title="Subir archivo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <input id="upload-file-input" type="file" onchange="uploadFileBrowser()" hidden>
      </label>
      <button class="icon-btn" onclick="browseTrash()" title="Papelera">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </button>
    </div>

    <div class="breadcrumb" id="breadcrumb">
      <span class="breadcrumb-item" onclick="browseRoot()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Raíz
      </span>
    </div>

    <div id="storage-info-bar" class="storage-bar" style="display:none">
      <div class="storage-bar-bg"><div class="storage-bar-fill" id="storage-fill" style="width:0%"></div></div>
      <div class="storage-bar-text" id="storage-text">Cargando...</div>
    </div>
    <div id="trash-actions" style="display:none"></div>

    <div id="file-grid" class="file-grid"></div>
    <div id="pagination" class="pagination"></div>
  </div>
  <div id="explorer-resize" class="v-resize"></div>

  <!-- ═══ CENTER: API TESTER ═══ -->
  <div class="panel-tester">
    <!-- Request Bar -->
    <div class="request-bar">
      <select id="req-method" class="method-select">
        <option value="GET" class="method-get">GET</option>
        <option value="POST" class="method-post">POST</option>
        <option value="PUT" class="method-put">PUT</option>
        <option value="PATCH" class="method-patch">PATCH</option>
        <option value="DELETE" class="method-delete">DELETE</option>
      </select>
      <input id="req-url" type="text" class="url-input" value="/api/me" placeholder="/api/...">
      <button class="send-btn" onclick="sendRequest()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Enviar
      </button>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <span class="quick-label">Rápidas:</span>
      <button class="quick-btn" onclick="quickAuth('GET','/me')">GET /me</button>
      <button class="quick-btn" onclick="quickAuth('POST','/refresh')">POST /refresh</button>
      <button class="quick-btn" onclick="quickAuth('GET','/storage/info')">GET /storage/info</button>
      <button class="quick-btn" onclick="quickAuth('GET','/storage/folder/content')">GET /folders</button>
      <button class="quick-btn" onclick="quickAuth('GET','/storage/trash')">GET /trash</button>
      <button class="quick-btn" onclick="quickAuth('POST','/logout')">POST /logout</button>
    </div>

    <!-- Request Tabs -->
    <div class="req-tabs">
      <div class="req-tab active" onclick="switchReqTab('body',this)">Body</div>
      <div class="req-tab" onclick="switchReqTab('headers',this)">Headers</div>
      <div class="req-tab" onclick="switchReqTab('auth',this)">Auth</div>
      <div class="req-tab" onclick="switchReqTab('helpers',this)">Helpers</div>
    </div>

    <div class="req-tab-content">
      <!-- Body Tab -->
      <div class="req-pane active" id="req-body-pane">
        <div class="body-type-row">
          <label class="radio-label"><input type="radio" name="body-type" value="json" checked onchange="switchBodyType(this.value)"> JSON</label>
          <label class="radio-label"><input type="radio" name="body-type" value="form" onchange="switchBodyType(this.value)"> Form Data</label>
          <label class="radio-label"><input type="radio" name="body-type" value="none" onchange="switchBodyType(this.value)"> None</label>
        </div>
        <div id="body-json-editor">
          <textarea id="req-body" placeholder='{"key": "value"}'></textarea>
        </div>
        <div id="body-form-editor" style="display:none">
          <div id="form-fields"></div>
          <button class="small-btn" onclick="addFormField()">+ Agregar campo</button>
        </div>
      </div>

      <!-- Headers Tab -->
      <div class="req-pane" id="req-headers-pane" style="display:none">
        <div id="header-fields"></div>
        <button class="small-btn" onclick="addHeaderField()">+ Agregar header</button>
      </div>

      <!-- Auth Tab -->
      <div class="req-pane" id="req-auth-pane" style="display:none">
        <div class="auth-info">
          <p>La autenticación se maneja automáticamente vía cookies de sesión.</p>
          <p>Usa el botón <strong>Login</strong> para iniciar sesión.</p>
          <div class="auth-actions">
            <button onclick="doMe()">GET /me</button>
            <button onclick="doRefresh()">POST /refresh</button>
          </div>
        </div>
      </div>

      <!-- Helpers Tab -->
      <div class="req-pane" id="req-helpers-pane" style="display:none">
        <div class="helpers-grid">
          <button class="quick-btn" onclick="openProfileModal()">Perfil</button>
          <button class="quick-btn" onclick="openExpansionsModal()">Expansiones</button>
          <button class="quick-btn" onclick="openShareTestModal()">Probar link</button>
          <button class="quick-btn" onclick="quickVerify()">Verificar</button>
          <button class="quick-btn" onclick="quickCheckName()">Check nombre</button>
          <button class="quick-btn" onclick="quickVersionCheck()">Chequear vers.</button>
        </div>
        <div id="helper-result" style="display:none"></div>
      </div>
    </div>

    <!-- Console (bottom) -->
    <div class="console-resize" id="console-resize"></div>
    <div class="console-area" id="console-area">
      <div class="console-header">
        <span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
          Console
        </span>
        <button class="icon-btn-sm" onclick="$('log').innerHTML=''" title="Limpiar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
        </button>
      </div>
      <div id="log"></div>
    </div>
  </div>
  <div id="sidebar-resize" class="v-resize"></div>

  <!-- ═══ RIGHT: RESPONSE ═══ -->
  <div class="sidebar" id="sidebar">
    <div class="response-area">
      <div class="response-header">
        <span class="response-title">Response</span>
        <span id="res-status" class="res-status"></span>
        <span id="res-time" class="res-time"></span>
        <button class="icon-btn" onclick="clearResponse()" title="Limpiar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </button>
        <button class="icon-btn" onclick="toggleSidebar()" title="Cerrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="response-tabs">
        <div class="res-tab active" onclick="switchResTab('body',this)">Body</div>
        <div class="res-tab" onclick="switchResTab('headers',this)">Headers</div>
      </div>
      <div id="res-body" class="response-body">
        <div class="empty-response">Envía una request para ver la respuesta</div>
      </div>
      <div id="res-headers" class="response-body" style="display:none"></div>
    </div>
  </div>
</div>

<!-- ═══ MODALS ═══ -->

<!-- Login Modal -->
<div id="login-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeLoginModal()">
  <div class="modal login-panel">
    <button class="modal-close" onclick="closeLoginModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h3>Iniciar Sesión</h3>
    <label>Email</label>
    <input id="login-email" type="email" value="test@test.com">
    <label>Contraseña</label>
    <input id="login-pass" type="password" value="password123">
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="doRegister()">Register</button>
      <button class="btn-primary" onclick="doLogin()">Login</button>
    </div>
  </div>
</div>

<!-- Upload Conflict Modal -->
<div id="upload-modal" class="modal-overlay hidden">
  <div class="modal">
    <h3>Conflicto de nombre</h3>
    <div class="modal-body" id="upload-modal-body"></div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closeUploadModal()">Cancelar</button>
      <button class="btn-primary" id="upload-modal-confirm" onclick="confirmUpload()">Subir</button>
    </div>
  </div>
</div>

<!-- Properties Modal -->
<div id="props-overlay" class="modal-overlay hidden" onclick="if(event.target===this)closeProps()">
  <div class="modal props-panel">
    <div class="modal-header-row">
      <h3 id="props-title">Propiedades</h3>
      <button class="modal-close" onclick="closeProps()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="props-tabs">
      <div class="props-tab active" onclick="switchPropsTab('general',this)">General</div>
      <div class="props-tab" onclick="switchPropsTab('details',this)">Detalles</div>
    </div>
    <div class="props-body" id="props-body"></div>
    <div class="props-actions" id="props-actions"></div>
  </div>
</div>

<!-- Move Modal -->
<div id="move-overlay" class="modal-overlay hidden" onclick="if(event.target===this)closeMoveModal()">
  <div class="modal move-panel">
    <div class="modal-header-row">
      <h3 id="move-title">Mover a...</h3>
      <button class="modal-close" onclick="closeMoveModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="move-body" id="move-body">
      <div class="move-folder selected" data-id="" onclick="selectMoveFolder(this,'')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Raíz
      </div>
    </div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closeMoveModal()">Cancelar</button>
      <button class="btn-primary" onclick="confirmMove()">Mover</button>
    </div>
  </div>
</div>

<!-- Share Link Modal -->
<div id="share-overlay" class="modal-overlay hidden" onclick="if(event.target===this)closeShareModal()">
  <div class="modal share-panel">
    <div class="modal-header-row">
      <h3 id="share-title">Compartir archivo</h3>
      <button class="modal-close" onclick="closeShareModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="share-body">
      <div id="share-form">
        <label>Expiración (opcional)</label>
        <input id="share-expires" type="datetime-local">
        <label>Máximo de descargas (opcional)</label>
        <input id="share-max-downloads" type="number" min="1" placeholder="Sin límite">
        <label>Contraseña (opcional)</label>
        <input id="share-password" type="password" placeholder="Sin contraseña">
      </div>
      <div id="share-result" style="display:none">
        <label>Link de compartición</label>
        <div class="share-link-row">
          <input id="share-link-url" type="text" readonly>
          <button class="share-copy-btn" onclick="copyShareLink()" title="Copiar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
        </div>
        <div id="share-meta" class="share-meta"></div>
      </div>
    </div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closeShareModal()">Cerrar</button>
      <button class="btn-primary" id="share-generate-btn" onclick="generateShareLink()">Generar link</button>
    </div>
  </div>
</div>

<!-- Profile Modal -->
<div id="profile-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeProfileModal()">
  <div class="modal profile-panel">
    <div class="modal-header-row">
      <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Perfil</h3>
      <button class="modal-close" onclick="closeProfileModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="profile-body">
      <div class="modal-section">
        <h4>Actualizar datos</h4>
        <label>Nombre</label>
        <input id="profile-name" type="text">
        <label>Apellido</label>
        <input id="profile-last-name" type="text">
        <div class="profile-actions">
          <button class="btn-primary" onclick="updateProfile()">Actualizar nombre</button>
        </div>
      </div>
      <div class="modal-section">
        <h4>Cambiar contraseña</h4>
        <label>Contraseña actual</label>
        <input id="profile-pass" type="password">
        <label>Nueva contraseña (mín. 8)</label>
        <input id="profile-new-pass" type="password">
        <div class="profile-actions">
          <button class="btn-primary" onclick="updatePassword()">Cambiar contraseña</button>
        </div>
      </div>
      <div class="modal-section danger-zone">
        <h4>Eliminar cuenta</h4>
        <p>Borrará tu cuenta y todos tus archivos de forma permanente. Esta acción no se puede deshacer.</p>
        <div class="profile-actions">
          <button class="btn-danger" onclick="promptDeleteAccount()">Eliminar cuenta</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Expansions Modal -->
<div id="expansions-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeExpansionsModal()">
  <div class="modal expansions-panel">
    <div class="modal-header-row">
      <h3>Expansiones</h3>
      <button class="modal-close" onclick="closeExpansionsModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="expansions-body" id="expansions-list"></div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closeExpansionsModal()">Cerrar</button>
    </div>
  </div>
</div>

<!-- Share Test Modal -->
<div id="share-test-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeShareTestModal()">
  <div class="modal share-test-panel">
    <div class="modal-header-row">
      <h3>Probar link compartido</h3>
      <button class="modal-close" onclick="closeShareTestModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="share-test-body">
      <label>Link compartido</label>
      <input id="share-test-link" type="text" placeholder="https://dominio.com/share/ab12...">
      <div class="share-test-actions">
        <button class="btn-primary" onclick="shareTestConfig()">Ver configuración</button>
        <button class="btn-primary" onclick="shareTestOpen()">Probar link</button>
      </div>
      <div id="share-test-config" style="display:none"></div>
    </div>
  </div>
</div>

<!-- Storage Picker Modal -->
<div id="picker-modal" class="modal-overlay hidden" onclick="if(event.target===this)closePickerModal()">
  <div class="modal picker-panel">
    <div class="modal-header-row">
      <h3 id="picker-title">Seleccionar archivo</h3>
      <button class="modal-close" onclick="closePickerModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="picker-body">
      <input id="picker-name-input" type="text" placeholder="Nombre a verificar" style="display:none">
      <div id="picker-list" class="picker-list"></div>
    </div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closePickerModal()">Cancelar</button>
    </div>
  </div>
</div>

<!-- Verify Modal -->
<div id="verify-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeVerifyModal()">
  <div class="modal verify-panel">
    <div class="modal-header-row">
      <h3>Verificar espacio</h3>
      <button class="modal-close" onclick="closeVerifyModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="share-test-body">
      <label>Tamaño</label>
      <div class="verify-size-row">
        <input id="verify-size-input" type="number" min="1" value="1">
        <select id="verify-size-unit">
          <option value="1">Bytes</option>
          <option value="1024">KB</option>
          <option value="1048576">MB</option>
          <option value="1073741824">GB</option>
        </select>
      </div>
    </div>
    <div class="modal-actions-row">
      <button class="btn-secondary" onclick="closeVerifyModal()">Cancelar</button>
      <button class="btn-primary" onclick="quickVerifyRun()">Verificar</button>
    </div>
  </div>
</div>

<!-- Context Menu -->
<div id="context-menu" class="context-menu"></div>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('upload-progress.js') }}"></script>
<script src="{{ asset('test.js') }}"></script>
</body>
</html>
