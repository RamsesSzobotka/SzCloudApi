# ☁️ Cloud Storage API — Diseño de Base de Datos

> Actualizado: 2026-08-19

## 1. Objetivo

Este documento define únicamente la estructura de la base de datos PostgreSQL para la Cloud Storage API.

La base de datos almacena **metadata, relaciones, permisos, versiones, auditoría y configuración de almacenamiento**, pero **no almacena el contenido binario de los archivos**.

Los archivos físicos serán almacenados en MinIO/S3.

---

# 2. Tablas principales

La base de datos inicial estará formada por:

```text
users
folders
files
file_versions
file_permissions
share_links
audit_logs
```

Como tablas adicionales para funcionalidades posteriores:

```text
expansions
user_expansions
upload_sessions
```

---

# 3. Diagrama de relaciones

```text
users
 │
 ├───────────────┐
 │               │
 ▼               ▼
folders         files
 │               │
 │               ├──────────────► file_versions
 │               │
 │               ├──────────────► file_permissions ◄──── users
 │               │
 │               └──────────────► share_links
 │
 └── parent_id ──► folders

users ───────────► audit_logs

users ◄─────────── user_expansions ────────────► expansions
```

---

# 4. Tabla `users`

Representa a los usuarios registrados en el sistema.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador único del usuario |
| `name` | `varchar(150)` | NOT NULL | Nombre del usuario |
| `email` | `varchar(255)` | NOT NULL, UNIQUE | Correo electrónico |
| `password` | `varchar(255)` | NOT NULL | Contraseña almacenada mediante hash |
| `storage_limit` | `bigint` | NOT NULL | Espacio máximo permitido en bytes |
| `storage_used` | `bigint` | NOT NULL | Espacio utilizado en bytes |
| `file_count` | `bigint` | NOT NULL | Cantidad de archivos |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |

### Valores iniciales

```text
storage_limit = 10737418240   (10 GB gratis)
storage_used = 0
file_count = 0
```

### Índices

```text
PRIMARY KEY (id)
UNIQUE (email)
```

---

# 5. Tabla `expansions`

Catálogo de expansiones de almacenamiento disponibles para compra.

Esta tabla es de referencia: si cambian precios o tamaños, solo se actualizan las filas.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `bigint` | PK, auto-increment | Identificador de la expansión |
| `name` | `varchar(100)` | NOT NULL, UNIQUE | Nombre de la expansión |
| `storage_bytes` | `bigint` | NOT NULL | Espacio que otorga en bytes |
| `price_cents` | `integer` | NOT NULL | Precio en centavos |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |

### Ejemplo

| name | storage_bytes | price_cents |
|------|---------------|-------------|
| 1GB | 1073741824 | 199 |
| 10GB | 10737418240 | 1499 |
| 50GB | 53687091200 | 5999 |
| 100GB | 107374182400 | 9999 |

---

# 6. Tabla `folders`

Representa las carpetas de cada usuario.

Permite construir una estructura jerárquica mediante `parent_id`.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador de la carpeta |
| `user_id` | `uuid` | FK, NOT NULL | Usuario propietario |
| `parent_id` | `uuid` | FK, NULL | Carpeta padre |
| `name` | `varchar(255)` | NOT NULL | Nombre de la carpeta |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |
| `deleted_at` | `timestamp` | NULL | Fecha de eliminación lógica |

## Relaciones

```text
folders.user_id
    ↓
users.id

folders.parent_id
    ↓
folders.id
```

`parent_id = NULL` representa una carpeta ubicada en la raíz del usuario.

## Restricciones recomendadas

Un usuario no debería poder tener dos carpetas con el mismo nombre dentro de la misma carpeta padre.

Conceptualmente:

```text
UNIQUE(user_id, parent_id, name)
```

---

# 7. Tabla `files`

Representa la metadata de los archivos.

El contenido binario del archivo **NO se almacena en esta tabla**.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador del archivo |
| `user_id` | `uuid` | FK, NOT NULL | Usuario propietario |
| `folder_id` | `uuid` | FK, NULL | Carpeta actual |
| `original_folder_id` | `uuid` | FK, NULL | Carpeta original para restauración |
| `original_name` | `varchar(255)` | NOT NULL | Nombre visible del archivo |
| `storage_name` | `varchar(255)` | NOT NULL, UNIQUE | Nombre físico generado |
| `storage_path` | `text` | NOT NULL | Ruta dentro de MinIO/S3 |
| `mime_type` | `varchar(255)` | NOT NULL | MIME type real |
| `extension` | `varchar(20)` | NULL | Extensión |
| `size` | `bigint` | NOT NULL | Tamaño en bytes |
| `hash` | `char(64)` | NULL | SHA-256 del archivo |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |
| `deleted_at` | `timestamp` | NULL | Fecha de eliminación lógica |

## Relaciones

```text
files.user_id
    ↓
users.id

files.folder_id
    ↓
folders.id

files.original_folder_id
    ↓
folders.id
```

## Ejemplo

```text
id:
8f0b4f0e-7c75-4b3c-a3c4-7c5e5f2f1234

original_name:
documento.pdf

storage_name:
2f2f1e6c-8c20-4dcb-a123-98f7a1f9c001.bin

storage_path:
users/8f0b4f0e-7c75-4b3c-a3c4-7c5e5f2f1234/files/2f2f1e6c-8c20-4dcb-a123-98f7a1f9c001.bin

mime_type:
application/pdf

extension:
pdf

size:
1520342

hash:
SHA256...
```

---

# 8. Tabla `file_versions`

Almacena las diferentes versiones de un archivo.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador de la versión |
| `file_id` | `uuid` | FK, NOT NULL | Archivo al que pertenece |
| `version` | `integer` | NOT NULL | Número de versión |
| `storage_name` | `varchar(255)` | NOT NULL | Nombre físico de esta versión |
| `storage_path` | `text` | NOT NULL | Ruta física en MinIO/S3 |
| `mime_type` | `varchar(255)` | NOT NULL | MIME type |
| `size` | `bigint` | NOT NULL | Tamaño en bytes |
| `hash` | `char(64)` | NULL | SHA-256 |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |

## Restricciones

```text
UNIQUE(file_id, version)
```

## Relación

```text
file_versions.file_id
    ↓
files.id
```

---

# 9. Tabla `file_permissions`

Representa los archivos compartidos entre usuarios.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador del permiso |
| `file_id` | `uuid` | FK, NOT NULL | Archivo compartido |
| `user_id` | `uuid` | FK, NOT NULL | Usuario que recibe el permiso |
| `permission` | `varchar(20)` | NOT NULL | Nivel de permiso |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |

## Valores permitidos

```text
viewer
editor
```

El propietario no necesita registrarse en esta tabla porque se obtiene desde:

```text
files.user_id
```

## Restricciones

```text
UNIQUE(file_id, user_id)
```

## Relaciones

```text
file_permissions.file_id
    ↓
files.id

file_permissions.user_id
    ↓
users.id
```

---

# 10. Tabla `share_links`

Representa enlaces públicos temporales para compartir archivos.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador del enlace |
| `file_id` | `uuid` | FK, NOT NULL | Archivo compartido |
| `token_hash` | `char(64)` | NOT NULL, UNIQUE | Hash del token público |
| `expires_at` | `timestamp` | NULL | Fecha de expiración |
| `max_downloads` | `integer` | NULL | Máximo de descargas permitidas |
| `download_count` | `integer` | NOT NULL | Cantidad de descargas |
| `password_hash` | `varchar(255)` | NULL | Password opcional |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |
| `revoked_at` | `timestamp` | NULL | Fecha de revocación |

## Reglas

Un enlace es válido cuando:

```text
revoked_at IS NULL
```

y:

```text
expires_at IS NULL
OR expires_at > NOW()
```

y:

```text
max_downloads IS NULL
OR download_count < max_downloads
```

## Relación

```text
share_links.file_id
    ↓
files.id
```

### Seguridad

El token original no debería almacenarse directamente.

Guardar:

```text
SHA-256(token)
```

en:

```text
token_hash
```

---

# 11. Tabla `audit_logs`

Registra las acciones importantes realizadas dentro del sistema.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador del registro |
| `user_id` | `uuid` | FK, NULL | Usuario que realizó la acción |
| `action` | `varchar(50)` | NOT NULL | Acción realizada |
| `resource_type` | `varchar(50)` | NULL | Tipo de recurso |
| `resource_id` | `uuid` | NULL | ID del recurso |
| `ip_address` | `inet` | NULL | Dirección IP |
| `user_agent` | `text` | NULL | User agent |
| `metadata` | `jsonb` | NULL | Información adicional |
| `created_at` | `timestamp` | NOT NULL | Fecha de la acción |

## Acciones iniciales

```text
LOGIN
LOGOUT

FILE_UPLOADED
FILE_DOWNLOADED
FILE_DELETED
FILE_RESTORED
FILE_RENAMED
FILE_MOVED

FILE_SHARED
PERMISSION_CHANGED

FOLDER_CREATED
FOLDER_RENAMED
FOLDER_MOVED
FOLDER_DELETED
```

## Relación

```text
audit_logs.user_id
    ↓
users.id
```

`user_id` puede ser `NULL` para acciones que no puedan asociarse a un usuario autenticado.

---

# 12. Tabla `user_expansions`

Historial de expansiones compradas por cada usuario.

Cada compra suma el `storage_bytes` de la expansión al `storage_limit` del usuario.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador |
| `user_id` | `uuid` | FK, NOT NULL | Usuario que compró |
| `expansion_id` | `bigint` | FK, NOT NULL | Expansión comprada |
| `created_at` | `timestamp` | NOT NULL | Fecha de compra |

## Relaciones

```text
user_expansions.user_id
    ↓
users.id

user_expansions.expansion_id
    ↓
expansions.id
```

## Regla de negocio

Las expansiones son acumulables. Ejemplo:
- Usuario compra 100GB + 50GB + 1GB + 1GB + 1GB + 1GB
- storage_limit = 10GB (base) + 100GB + 50GB + 1GB + 1GB + 1GB + 1GB = 154GB

---

# 13. Tabla `upload_sessions`

Tabla opcional para implementar posteriormente cargas grandes o multipart/chunk uploads.

## Campos

| Campo | PostgreSQL | Restricciones | Descripción |
|---|---|---|---|
| `id` | `uuid` | PK | Identificador de sesión |
| `user_id` | `uuid` | FK, NOT NULL | Usuario |
| `folder_id` | `uuid` | FK, NULL | Carpeta destino |
| `file_name` | `varchar(255)` | NOT NULL | Nombre original |
| `mime_type` | `varchar(255)` | NOT NULL | MIME type |
| `total_size` | `bigint` | NOT NULL | Tamaño total esperado |
| `uploaded_size` | `bigint` | NOT NULL | Tamaño actualmente subido |
| `storage_path` | `text` | NULL | Ubicación temporal |
| `status` | `varchar(30)` | NOT NULL | Estado |
| `expires_at` | `timestamp` | NOT NULL | Expiración de la sesión |
| `created_at` | `timestamp` | NOT NULL | Fecha de creación |
| `updated_at` | `timestamp` | NOT NULL | Última actualización |

## Estados

```text
pending
uploading
completed
failed
cancelled
expired
```

---

# 14. Relaciones completas

## Users

```text
users
 ├── hasMany folders
 ├── hasMany files
 ├── hasMany file_permissions
 ├── hasMany share_links (indirectamente mediante files)
 ├── hasMany audit_logs
 ├── hasMany user_expansions
 └── belongsToMany expansions (via user_expansions)
```

## Folders

```text
folders
 ├── belongsTo user
 ├── belongsTo parent
 ├── hasMany children
 └── hasMany files
```

## Files

```text
files
 ├── belongsTo user
 ├── belongsTo folder
 ├── belongsTo original_folder
 ├── hasMany versions
 ├── hasMany permissions
 └── hasMany share_links
```

## File Versions

```text
file_versions
 └── belongsTo file
```

## File Permissions

```text
file_permissions
 ├── belongsTo file
 └── belongsTo user
```

## Share Links

```text
share_links
 └── belongsTo file
```

## Audit Logs

```text
audit_logs
 └── belongsTo user
```

---

# 15. Claves foráneas

```text
folders.user_id
    → users.id

folders.parent_id
    → folders.id

files.user_id
    → users.id

files.folder_id
    → folders.id

files.original_folder_id
    → folders.id

file_versions.file_id
    → files.id

file_permissions.file_id
    → files.id

file_permissions.user_id
    → users.id

share_links.file_id
    → files.id

audit_logs.user_id
    → users.id

user_expansions.user_id
    → users.id

user_expansions.expansion_id
    → expansions.id

upload_sessions.user_id
    → users.id

upload_sessions.folder_id
    → folders.id
```

---

# 16. Comportamiento al eliminar registros

## Users

No se recomienda eliminar físicamente usuarios que tengan historial.

Puede utilizarse:

```text
deleted_at
```

si posteriormente se requiere soft delete de usuarios.

## Folders

Utilizar:

```text
deleted_at
```

para implementar papelera.

## Files

Utilizar:

```text
deleted_at
```

para implementar papelera.

El archivo físico no debe eliminarse inmediatamente si se necesita restauración.

## File Versions

Las versiones pueden mantenerse mientras exista el archivo.

## File Permissions

Si se elimina el archivo:

```text
file_permissions
```

deberá eliminarse mediante:

```text
ON DELETE CASCADE
```

## Share Links

Si se elimina permanentemente un archivo:

```text
share_links
```

deberá eliminarse mediante:

```text
ON DELETE CASCADE
```

---

# 17. Índices recomendados

## Users

```sql
UNIQUE(email)
```

## Folders

```sql
INDEX(user_id)
INDEX(parent_id)
INDEX(user_id, parent_id)
```

## Files

```sql
INDEX(user_id)
INDEX(folder_id)
INDEX(user_id, folder_id)
INDEX(mime_type)
INDEX(extension)
INDEX(created_at)
INDEX(deleted_at)
INDEX(hash)
```

## File Versions

```sql
INDEX(file_id)
UNIQUE(file_id, version)
```

## File Permissions

```sql
INDEX(user_id)
INDEX(file_id)
UNIQUE(file_id, user_id)
```

## Share Links

```sql
UNIQUE(token_hash)
INDEX(file_id)
INDEX(expires_at)
```

## Audit Logs

```sql
INDEX(user_id)
INDEX(resource_type, resource_id)
INDEX(action)
INDEX(created_at)
```

---

# 18. Tipos PostgreSQL recomendados

Para identificadores:

```text
uuid
```

Para tamaños de archivos:

```text
bigint
```

Para SHA-256:

```text
char(64)
```

Para metadata flexible:

```text
jsonb
```

Para IP:

```text
inet
```

Para fechas:

```text
timestamp
```

Para valores booleanos:

```text
boolean
```

---

# 19. Orden recomendado de migraciones

Para evitar problemas con claves foráneas:

```text
1. users
2. folders
3. files
4. file_versions
5. file_permissions
6. share_links
7. audit_logs
8. expansions
9. user_expansions
10. upload_sessions
```

---

# 20. Base mínima para el MVP

Para comenzar el proyecto no es necesario implementar todas las tablas.

La primera versión puede utilizar solamente:

```text
users
folders
files
```

Después:

```text
file_versions
file_permissions
share_links
audit_logs
```

Y finalmente:

```text
expansions
user_expansions
upload_sessions
```

---

# 21. Estructura final

```text
                         ┌─────────────┐
                         │  expansions │
                         └──────┬──────┘
                                │
                                ▼
                         ┌─────────────┐
                         │    users    │
                         └──────┬──────┘
                                │
               ┌────────────────┼────────────────┐
               │                │                │
               ▼                ▼                ▼
          ┌─────────┐      ┌─────────┐     ┌──────────────┐
          │ folders │      │  files  │     │ audit_logs   │
          └────┬────┘      └────┬────┘     └──────────────┘
               │                │
               │          ┌─────┼──────────────┐
               │          │     │              │
               │          ▼     ▼              ▼
               │     ┌────────┐ ┌────────────────┐
               │     │versions│ │ permissions    │
               │     └────────┘ └────────────────┘
               │
               │          ┌──────────────┐
               └─────────►│ share_links  │
                          └──────────────┘

               ┌──────────────────┐
               │ user_expansions  │
               └──────────────────┘

               ┌──────────────────┐
               │ upload_sessions  │
               └──────────────────┘
```

---

# 22. Nota sobre MinIO/S3

La base de datos únicamente debe guardar referencias al objeto almacenado.

Ejemplo:

```text
PostgreSQL
────────────────────────────────────
storage_name = 8a7c1e3f-....bin
storage_path = users/{user}/files/8a7c1e3f-....bin
size = 1520342
mime_type = application/pdf
hash = ...
```

Mientras que MinIO almacena:

```text
users/
└── {user_id}/
    └── files/
        └── 8a7c1e3f-....bin
```

La separación queda:

```text
PostgreSQL
    ↓
Metadata + relaciones + permisos

MinIO
    ↓
Contenido binario
```

Esta estructura permite posteriormente cambiar MinIO por Amazon S3 u otro storage compatible sin modificar el modelo principal de la aplicación.
