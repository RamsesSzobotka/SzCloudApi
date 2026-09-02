<?php

namespace App\Http\Swagger;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Info, SecurityScheme, and Server definitions.
 */
#[OA\Info(
    title: 'SzCloudApi',
    version: '1.0.0',
    description: 'API para almacenamiento en la nube — gestión de archivos, carpetas, papelera, etc. **Autenticación via cookies:** registrarse con `POST /api/register` e iniciar sesión con `POST /api/login`. Swagger automáticamente gestiona el token de sesión.'
)]
#[OA\Server(url: '/', description: 'Servidor de desarrollo')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Autenticación via cookies httponly. Regístrate en POST /api/register e inicia sesión en POST /api/login — Swagger gestiona el token automáticamente.',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'Auth', description: 'Registro, login y gestión de sesión')]
#[OA\Tag(name: 'User Management', description: 'Actualización y eliminación de usuario')]
#[OA\Tag(name: 'Folders', description: 'CRUD de carpetas — crear, renombrar, mover, restaurar')]
#[OA\Tag(name: 'Files', description: 'CRUD de archivos — renombrar, mover, restaurar, eliminar. **Subida simple:** incluye `POST /api/storage/file` para subir el archivo completo de una sola vez. Es la forma más fácil de implementar pero más lenta y sin progreso de subida.')]
#[OA\Tag(name: 'Upload', description: '**Subida por chunks (multipart):** flujo en 3 pasos — `init` → `chunk` (repetir) → `complete`. Más complejo de implementar pero más eficiente (paralelizable, reanudable) y permite rastrear el progreso de subida en tiempo real. Usar para archivos grandes o cuando se necesita barra de progreso.')]
#[OA\Tag(name: 'Storage', description: 'Información de almacenamiento — uso, límites del plan y verificación de espacio')]
#[OA\Tag(name: 'Expansions', description: 'Expansiones de almacenamiento — catálogo, detalles y compra de espacio adicional')]
#[OA\Tag(name: 'Trash', description: 'Papelera — listar, vaciar, eliminación permanente')]
#[OA\Tag(name: 'ShareLinks', description: 'Enlaces de compartir — crear, acceder, configurar')]
class Swagger {}
