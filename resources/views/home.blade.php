<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SzCloud — Modern File Storage API</title>
    <meta name="description" content="A modern REST API for file management, object storage, and file versioning.">
    @if (file_exists(public_path('build/manifest.json')) || file_exists('hot'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { background: #0a0a0a; color: #ededec; }
        .code-block { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body class="min-h-screen antialiased">

    <!-- Navbar -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-[#0a0a0a]/80 backdrop-blur-md border-b border-white/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="text-lg font-semibold tracking-tight text-white">SzCloud</a>
                <div class="hidden md:flex items-center gap-6">
                    <a href="/api/documentation" class="text-sm text-[#a1a09a] hover:text-white transition-colors">Documentation</a>
                    <a href="/api/documentation" class="text-sm text-[#a1a09a] hover:text-white transition-colors">Swagger</a>
                    <a href="/test" class="text-sm text-[#a1a09a] hover:text-white transition-colors">API Test</a>
                </div>
                <button id="menu-toggle" class="md:hidden text-[#a1a09a] hover:text-white" aria-label="Toggle menu">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path id="menu-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path id="menu-close" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" class="hidden"/>
                    </svg>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden border-t border-white/5 bg-[#0a0a0a]/95 backdrop-blur-md">
            <div class="px-4 py-4 space-y-3">
                <a href="/api/documentation" class="block text-sm text-[#a1a09a] hover:text-white">Documentation</a>
                <a href="/api/documentation" class="block text-sm text-[#a1a09a] hover:text-white">Swagger</a>
                <a href="/test" class="block text-sm text-[#a1a09a] hover:text-white">API Test</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto text-center">
            <span class="inline-block px-3 py-1 text-xs font-medium text-[#a1a09a] border border-white/10 rounded-full mb-6">REST API</span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white mb-6">
                Modern File Storage API
            </h1>
            <p class="text-lg text-[#a1a09a] max-w-2xl mx-auto mb-10">
                A complete REST API for file management, object storage with MinIO/S3, and version control. Build secure, scalable file workflows with JWT authentication.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/api/documentation" class="inline-flex items-center px-6 py-3 bg-white text-[#0a0a0a] font-medium text-sm rounded-lg hover:bg-white/90 transition-colors">
                    View Documentation
                </a>
                <a href="/api/documentation" class="inline-flex items-center px-6 py-3 border border-white/10 text-white font-medium text-sm rounded-lg hover:bg-white/5 transition-colors">
                    Open Swagger
                </a>
                <a href="/test" class="inline-flex items-center px-6 py-3 border border-white/10 text-white font-medium text-sm rounded-lg hover:bg-white/5 transition-colors">
                    Try API
                </a>
            </div>
        </div>
    </section>

    <!-- Tech Stack -->
    <section class="py-12 border-y border-white/5">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-[#706f6c]">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    Laravel
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    REST API
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    JWT
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    MySQL
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    MinIO / S3
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    OpenAPI
                </span>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <h2 class="text-2xl font-bold text-white text-center mb-4">Built for Developers</h2>
            <p class="text-[#a1a09a] text-center mb-12 max-w-xl mx-auto">Everything you need to manage files, storage, and access control in one API.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 rounded-xl border border-white/5 bg-white/[0.02]">
                    <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#a1a09a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Authentication</h3>
                    <p class="text-sm text-[#706f6c]">JWT-based auth and authorization. Register, login, refresh tokens, and manage user profiles.</p>
                </div>
                <div class="p-6 rounded-xl border border-white/5 bg-white/[0.02]">
                    <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#a1a09a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">File Management</h3>
                    <p class="text-sm text-[#706f6c]">Full CRUD operations for files and folders. Rename, move, restore, and manage trash.</p>
                </div>
                <div class="p-6 rounded-xl border border-white/5 bg-white/[0.02]">
                    <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#a1a09a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Object Storage</h3>
                    <p class="text-sm text-[#706f6c]">MinIO/S3-compatible storage backend. Scalable, reliable file storage with expansion plans.</p>
                </div>
                <div class="p-6 rounded-xl border border-white/5 bg-white/[0.02]">
                    <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#a1a09a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">File Versioning</h3>
                    <p class="text-sm text-[#706f6c]">Track and restore file versions. Navigate history with forward/back version support.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture -->
    <section class="py-20 px-4 sm:px-6 lg:px-8 border-t border-white/5">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white text-center mb-12">Architecture</h2>
            <div class="code-block rounded-xl border border-white/10 bg-[#111] p-6 sm:p-8 overflow-x-auto">
                <pre class="text-xs sm:text-sm text-[#a1a09a] leading-relaxed">
┌──────────┐       ┌──────────────┐       ┌─────────────────────────────┐       ┌──────────┐
│          │       │              │       │         Laravel             │       │          │
│  Client  │──────▶│  REST API    │──────▶│  ┌───────┐ ┌────────────┐ │──────▶│ Database │
│          │◀──────│  (HTTP/JSON) │◀──────│  │ Auth  │ │   Files    │ │◀──────│ (MySQL)  │
└──────────┘       └──────────────┘       │  └───────┘ └────────────┘ │       └──────────┘
                                          │  ┌───────┐ ┌────────────┐ │
                                          │  │Share  │ │ Versioning │ │──────▶┌──────────┐
                                          │  └───────┘ └────────────┘ │       │  MinIO   │
                                          └─────────────────────────────┘       │   /S3    │
                                                                              └──────────┘</pre>
            </div>
        </div>
    </section>

    <!-- API Preview -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white text-center mb-12">API in Action</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-xl border border-white/10 bg-[#111] overflow-hidden">
                    <div class="px-4 py-3 border-b border-white/5 flex items-center gap-2">
                        <span class="px-2 py-0.5 text-xs font-medium bg-green-500/10 text-green-400 rounded">POST</span>
                        <span class="text-sm text-[#a1a09a]">/api/login</span>
                    </div>
                    <pre class="p-4 text-xs text-[#a1a09a] code-block overflow-x-auto">{
  "email": "user@example.com",
  "password": "secret"
}</pre>
                </div>
                <div class="rounded-xl border border-white/10 bg-[#111] overflow-hidden">
                    <div class="px-4 py-3 border-b border-white/5">
                        <span class="text-sm text-[#706f6c]">Response <span class="text-green-400">200</span></span>
                    </div>
                    <pre class="p-4 text-xs text-[#a1a09a] code-block overflow-x-auto">{
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Doe"
  }
}</pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20 px-4 sm:px-6 lg:px-8 border-t border-white/5">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white text-center mb-12">Quick Start</h2>
            <div class="rounded-xl border border-white/10 bg-[#111] p-6 overflow-x-auto">
                <pre class="text-sm text-[#a1a09a] code-block"><span class="text-[#706f6c]"># Authenticate and get your token</span>
<span class="text-green-400">curl</span> -X POST {{ config('app.url', 'http://localhost:8000') }}/api/login \
  -H <span class="text-yellow-300">"Content-Type: application/json"</span> \
  -d <span class="text-yellow-300">'{"email":"user@example.com","password":"secret"}'</span>

<span class="text-[#706f6c]"># Use the token for authenticated requests</span>
<span class="text-green-400">curl</span> {{ config('app.url', 'http://localhost:8000') }}/api/me \
  -H <span class="text-yellow-300">"Authorization: Bearer YOUR_TOKEN"</span></pre>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Ready to explore the API?</h2>
            <p class="text-[#a1a09a] mb-8">Dive into the full documentation or try it live with Swagger.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/api/documentation" class="inline-flex items-center px-6 py-3 bg-white text-[#0a0a0a] font-medium text-sm rounded-lg hover:bg-white/90 transition-colors">
                    Read Documentation
                </a>
                <a href="/api/documentation" class="inline-flex items-center px-6 py-3 border border-white/10 text-white font-medium text-sm rounded-lg hover:bg-white/5 transition-colors">
                    Open Swagger
                </a>
                <a href="/test" class="inline-flex items-center px-6 py-3 border border-white/10 text-white font-medium text-sm rounded-lg hover:bg-white/5 transition-colors">
                    Try API
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-8 border-t border-white/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span class="text-sm text-[#706f6c]">SzCloud &copy; 2026</span>
            <div class="flex items-center gap-6">
                <a href="/api/documentation" class="text-sm text-[#706f6c] hover:text-white transition-colors">Documentation</a>
                <a href="/api/documentation" class="text-sm text-[#706f6c] hover:text-white transition-colors">Swagger</a>
                <a href="/test" class="text-sm text-[#706f6c] hover:text-white transition-colors">API Test</a>
            </div>
        </div>
    </footer>

    <script>
        const toggle = document.getElementById('menu-toggle');
        const menu = document.getElementById('mobile-menu');
        const openIcon = document.getElementById('menu-open');
        const closeIcon = document.getElementById('menu-close');
        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
            openIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        });
    </script>
</body>
</html>
