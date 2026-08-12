# ☁️ Cloud Storage API — Especificación del proyecto

## 1. Objetivo

Construir una **API REST de almacenamiento de archivos en la nube** utilizando PHP/Laravel.

La API permitirá a los usuarios:

* Registrarse e iniciar sesión.
* Subir archivos.
* Descargar archivos.
* Crear carpetas.
* Mover archivos entre carpetas.
* Renombrar archivos y carpetas.
* Eliminar y restaurar archivos.
* Compartir archivos con otros usuarios.
* Generar enlaces públicos temporales.
* Consultar el espacio utilizado.
* Mantener versiones de archivos.
* Consultar historial de acciones.

El sistema debe separar claramente:

```text
Metadata
    ↓
PostgreSQL

Archivos físicos
    ↓
S3 / MinIO
```

La base de datos **no debe almacenar el contenido binario de los archivos**.

---

# 2. Stack obligatorio

### Backend

* PHP 8.4+
* Laravel
* Laravel API
* JWT para autenticación

### Base de datos

* PostgreSQL

### Storage

* MinIO inicialmente
* Diseñado para ser compatible con Amazon S3

### Infraestructura

* Docker
* Docker Compose

### Complementos

* Redis
* OpenAPI / Swagger
* PHPUnit o Pest

---

# 3. Arquitectura

Utiliza una arquitectura por capas:

```text
Request
   ↓
Controller
   ↓
Service
   ↓
Repository
   ↓
Model
   ↓
PostgreSQL
```

Para archivos:

```text
Controller
   ↓
FileService
   ↓
StorageService
   ↓
S3 / MinIO
```

No coloques toda la lógica dentro de los Controllers.

Por ejemplo:

```php
FileController
```

debe encargarse principalmente de:

```text
recibir request
validar/autorización
llamar servicio
devolver response
```

Mientras que:

```php
FileService
```

maneja:

```text
subida
metadata
hash
storage
versiones
permisos
```

---

# 4. Autenticación

Implementa:

```http
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
GET  /api/auth/me
```

### Registro

```json
{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "password": "Password123!"
}
```

Requisitos:

* Email único.
* Password hasheado.
* Validación de contraseña.
* JWT.
* No devolver password.
* No guardar passwords en texto plano.

---

# 5. Usuarios

Cada usuario tendrá un espacio de almacenamiento independiente.

Ejemplo:

```text
User A
 └── 5 GB

User B
 └── 5 GB
```

La API debe impedir que un usuario pueda acceder a recursos pertenecientes a otro usuario.

Esto es **muy importante**.

No basta con:

```php
File::find($id);
```

Debe comprobarse ownership/permisos.

Por ejemplo:

```php
File::where('id', $id)
    ->where('user_id', auth()->id())
    ->firstOrFail();
```

o mediante una política de Laravel.

---

# 6. Carpetas

Endpoints:

```http
POST   /api/folders
GET    /api/folders
GET    /api/folders/{id}
PATCH  /api/folders/{id}
DELETE /api/folders/{id}
```

Crear:

```json
{
    "name": "Documentos",
    "parent_id": null
}
```

Debe permitir estructuras como:

```text
Root
│
├── Documents
│   ├── University
│   │   └── Projects
│   │
│   └── Personal
│
├── Images
│
└── Projects
```

Utiliza:

```text
parent_id
```

para representar la jerarquía.

---

# 7. Archivos

Endpoints principales:

```http
POST   /api/files
GET    /api/files
GET    /api/files/{id}
PATCH  /api/files/{id}
DELETE /api/files/{id}
GET    /api/files/{id}/download
```

La subida debe utilizar:

```http
multipart/form-data
```

Ejemplo:

```text
file = documento.pdf
folder_id = UUID
```

---

# 8. Metadata

Cuando se suba un archivo debes almacenar algo similar a:

```text
id
user_id
folder_id
original_name
storage_name
storage_path
mime_type
extension
size
hash
created_at
updated_at
```

Por ejemplo:

```json
{
    "id": "uuid",
    "name": "documento.pdf",
    "mime_type": "application/pdf",
    "extension": "pdf",
    "size": 1520342
}
```

---

# 9. Nombre físico del archivo

No guardes físicamente:

```text
storage/documento.pdf
```

Utiliza un identificador aleatorio.

Por ejemplo:

```text
storage/
└── 8a7c1e3f-....bin
```

La base de datos mantiene:

```text
original_name = documento.pdf
storage_name = 8a7c1e3f-....bin
```

Esto evita problemas con:

* nombres duplicados;
* caracteres especiales;
* path traversal;
* colisiones.

---

# 10. Seguridad de archivos

Este apartado debe ser importante en tu proyecto.

Validar:

### Tamaño

Por ejemplo:

```text
Máximo: 100 MB
```

### MIME type

Permitir inicialmente:

```text
image/jpeg
image/png
image/webp

application/pdf

text/plain

application/zip

application/vnd.openxmlformats-officedocument.wordprocessingml.document
```

Puedes ampliar la lista después.

### Nunca confiar únicamente en la extensión

No debes asumir que:

```text
archivo.jpg
```

es realmente una imagen.

Valida el MIME real del archivo.

---

# 11. Límite de almacenamiento

Cada usuario tendrá:

```text
storage_limit
storage_used
```

Por ejemplo:

```text
Free Plan

storage_limit = 5 GB
```

Antes de subir:

```text
storage_used + file_size <= storage_limit
```

Si excede:

```http
413 Payload Too Large
```

o una respuesta de negocio equivalente.

---

# 12. Eliminación

No borres inmediatamente los archivos.

Implementa papelera.

```http
DELETE /api/files/{id}
```

debería hacer:

```text
deleted_at = current timestamp
```

y mover lógicamente el archivo a:

```text
Trash
```

Endpoints:

```http
GET    /api/trash
POST   /api/trash/{id}/restore
DELETE /api/trash/{id}/permanent
```

---

# 13. Restauración

Cuando se restaure:

```http
POST /api/trash/{id}/restore
```

debe regresar a su carpeta original.

Debes guardar suficiente información para saber:

```text
original_folder_id
```

---

# 14. Compartir archivos

Un usuario puede compartir un archivo con otro usuario.

```http
POST /api/files/{id}/permissions
GET  /api/files/{id}/permissions
PATCH /api/files/{id}/permissions/{user}
DELETE /api/files/{id}/permissions/{user}
```

Permisos:

```text
owner
editor
viewer
```

### Owner

Puede:

* descargar;
* modificar;
* eliminar;
* compartir;
* cambiar permisos.

### Editor

Puede:

* descargar;
* modificar.

### Viewer

Puede:

* descargar;
* visualizar metadata.

---

# 15. Enlaces públicos

Permitir:

```http
POST /api/files/{id}/share-links
```

Respuesta:

```json
{
    "token": "a8f72bc91...",
    "expires_at": "2026-08-20T20:00:00Z"
}
```

Acceso:

```http
GET /api/share/{token}
```

Opcionalmente:

```json
{
    "expires_at": "...",
    "max_downloads": 5,
    "password": "..."
}
```

El token debe ser **criptográficamente seguro**.

No utilices:

```php
rand()
```

para generar tokens.

---

# 16. Descargas

Para archivos privados:

```http
GET /api/files/{id}/download
```

La API debe verificar:

```text
¿Es propietario?
        ↓ no
¿Tiene permiso?
        ↓ no
403 Forbidden
```

Para S3/MinIO puedes generar posteriormente una **URL temporal firmada** para descargar el archivo directamente desde el storage.

---

# 17. Versionado

Cuando un usuario reemplace un archivo:

```text
document.pdf
```

no elimines inmediatamente la versión anterior.

Tendrás:

```text
document.pdf

Version 1
Version 2
Version 3
```

Tabla:

```text
file_versions
```

Campos:

```text
id
file_id
version
storage_path
size
hash
created_at
```

Endpoints:

```http
GET /api/files/{id}/versions
GET /api/files/{id}/versions/{version}
POST /api/files/{id}/versions/{version}/restore
```

---

# 18. Hash y deduplicación

Al subir:

```text
SHA-256(file)
```

Guardar:

```text
hash
```

Esto permite detectar:

```text
archivo A
SHA256 = ABC123

archivo B
SHA256 = ABC123
```

Son idénticos.

Puedes implementar primero solamente la detección y posteriormente optimizar el almacenamiento mediante deduplicación.

---

# 19. Redis

Utiliza Redis para:

### Cache

Por ejemplo:

```text
storage_usage:{user_id}
```

### Rate limiting

Por ejemplo:

```text
100 requests/minute
```

### Jobs

Para operaciones pesadas.

---

# 20. Jobs

No hagas determinadas operaciones pesadas directamente dentro del request.

Puedes implementar:

```text
Upload
   ↓
Job
   ↓
CalculateHash
   ↓
UpdateMetadata
```

Otros jobs:

```text
DeleteExpiredFiles
CleanupTrash
CalculateStorageUsage
DeleteOldVersions
```

---

# 21. Auditoría

Crear:

```text
audit_logs
```

Registrar acciones importantes:

```text
LOGIN
FILE_UPLOADED
FILE_DOWNLOADED
FILE_DELETED
FILE_RESTORED
FILE_SHARED
FILE_RENAMED
FOLDER_CREATED
PERMISSION_CHANGED
```

Ejemplo:

```json
{
    "user_id": "...",
    "action": "FILE_UPLOADED",
    "resource_type": "file",
    "resource_id": "...",
    "ip_address": "...",
    "metadata": {}
}
```

---

# 22. Paginación

No devuelvas:

```http
GET /files
```

con absolutamente todos los archivos.

Utiliza:

```http
GET /files?page=1&per_page=20
```

Respuesta:

```json
{
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 150
    }
}
```

---

# 23. Búsqueda

Implementa:

```http
GET /files?search=document
```

Y filtros:

```text
folder_id
mime_type
extension
min_size
max_size
created_from
created_to
```

Ejemplo:

```http
GET /api/files?search=invoice&mime_type=application/pdf
```

---

# 24. Manejo de errores

Define respuestas consistentes.

Ejemplo:

```json
{
    "message": "File not found",
    "code": "FILE_NOT_FOUND"
}
```

Errores:

```text
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
413 Payload Too Large
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
```

No devuelvas excepciones internas ni información sensible al cliente.

---

# 25. Base de datos mínima

Inicialmente:

```text
users
folders
files
file_versions
file_permissions
share_links
audit_logs
```

Posteriormente:

```text
upload_sessions
plans
storage_usage
```

Relaciones:

```text
User
 ├── folders
 ├── files
 ├── permissions
 ├── share_links
 └── audit_logs

Folder
 ├── parent
 ├── children
 └── files

File
 ├── folder
 ├── owner
 ├── versions
 ├── permissions
 └── share_links
```

---

# 26. Docker

Tu `docker-compose.yml` debería terminar teniendo algo parecido a:

```text
services:

  api
  postgres
  redis
  minio
```

Opcionalmente:

```text
  worker
```

Arquitectura:

```text
             ┌────────────┐
             │   Laravel  │
             └─────┬──────┘
                   │
        ┌──────────┼───────────┐
        ▼          ▼           ▼
   PostgreSQL    Redis       MinIO
```

---

# 27. API Documentation

Documenta absolutamente todos los endpoints mediante OpenAPI/Swagger.

Debe poder verse:

```text
Authentication
    POST /auth/register
    POST /auth/login

Files
    POST /files
    GET /files
    GET /files/{id}
    ...

Folders
    POST /folders
    GET /folders
    ...

Sharing
    POST /files/{id}/permissions
    POST /files/{id}/share-links
```

---

# 28. Tests

Como proyecto de portafolio, los tests son importantes.

### Unit tests

Por ejemplo:

```text
StorageServiceTest
FileServiceTest
ShareLinkServiceTest
StorageQuotaServiceTest
```

### Feature tests

```text
UserCanUploadFile
UserCannotAccessAnotherUsersFile
UserCanDeleteFile
UserCanRestoreFile
UserCanShareFile
ViewerCannotDeleteFile
ExpiredShareLinkCannotBeUsed
StorageLimitIsEnforced
```

Uno de los tests más importantes:

```text
User A cannot access User B's files
```

---

# 29. Fases de desarrollo

No intentes hacer todo simultáneamente.

### Fase 1 — Base

```text
Laravel
PostgreSQL
Docker
JWT
Users
```

### Fase 2 — Storage

```text
MinIO
Upload
Download
Metadata
Delete
```

### Fase 3 — Organización

```text
Folders
Move
Rename
Trash
Restore
```

### Fase 4 — Seguridad

```text
Policies
Permissions
Share links
Expiration
Rate limiting
```

### Fase 5 — Features avanzadas

```text
Versions
Hash
Deduplication
Storage quotas
```

### Fase 6 — Infraestructura

```text
Redis
Queues
Jobs
Workers
```

### Fase 7 — Calidad

```text
Tests
Swagger
Logging
Audit
Error handling
```

### Fase 8 — Deployment

```text
Docker
CI/CD
Production
S3
HTTPS
Environment variables
```

---

# 🎯 Requisitos para considerar terminado el MVP

Yo pondría como condición mínima:

* [ ] Registro/login JWT.
* [ ] PostgreSQL.
* [ ] Docker.
* [ ] MinIO.
* [ ] Subir archivos.
* [ ] Descargar archivos.
* [ ] Eliminar archivos.
* [ ] Carpetas.
* [ ] Mover archivos.
* [ ] Renombrar archivos.
* [ ] Papelera.
* [ ] Restauración.
* [ ] Control de ownership.
* [ ] Validación MIME.
* [ ] Límite de tamaño.
* [ ] Cuota de almacenamiento.
* [ ] Paginación.
* [ ] Búsqueda.
* [ ] Manejo consistente de errores.
* [ ] Swagger.
* [ ] Tests.

Después:

* [ ] Compartir archivos.
* [ ] Permisos.
* [ ] Share links.
* [ ] URLs temporales.
* [ ] Versionado.
* [ ] Redis.
* [ ] Queues.
* [ ] Auditoría.
* [ ] Deduplicación.
* [ ] Upload por chunks.

## 🏆 Resultado final

El proyecto debería poder demostrar algo como:

```text
                   CLOUD STORAGE API

                        Client
                          │
                          ▼
                    Laravel API
                          │
              ┌───────────┼───────────┐
              │           │           │
              ▼           ▼           ▼
           Auth        Files       Sharing
              │           │           │
              └───────────┼───────────┘
                          │
              ┌───────────┼───────────┐
              ▼           ▼           ▼
         PostgreSQL     Redis       MinIO
              │           │           │
              ▼           ▼           ▼
          Metadata      Cache       Files
```

Y lo más importante para tu portafolio: **no presentes esto como "una aplicación para subir archivos"**. Preséntalo como una **RESTful Cloud Storage API**, donde el foco está en **seguridad, autorización, almacenamiento S3-compatible, procesamiento asíncrono y escalabilidad**.