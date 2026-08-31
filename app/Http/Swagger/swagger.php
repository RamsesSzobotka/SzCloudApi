<?php

namespace App\Http\Swagger;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Info, SecurityScheme, and Server definitions.
 */
#[OA\Info(
    title: 'SzCloudApi',
    version: '1.0.0',
    description: 'API para almacenamiento en la nube — gestión de archivos, carpetas, papelera, etc.'
)]
#[OA\Server(url: '/', description: 'Servidor de desarrollo')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Autenticación JWT. Usa el botón Authorize con email y contraseña para obtener el token automáticamente.',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'Auth', description: 'Registro, login y gestión de sesión')]
#[OA\Tag(name: 'User Management', description: 'Actualización y eliminación de usuario')]
#[OA\Tag(name: 'Folders', description: 'CRUD de carpetas — crear, renombrar, mover, restaurar')]
#[OA\Tag(name: 'Files', description: 'CRUD de archivos — subir, renombrar, mover, restaurar')]
#[OA\Tag(name: 'Trash', description: 'Papelera — listar, vaciar, eliminación permanente')]
class Swagger {}
