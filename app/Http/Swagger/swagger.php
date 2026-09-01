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
#[OA\Tag(name: 'Files', description: 'CRUD de archivos — subir, renombrar, mover, restaurar')]
#[OA\Tag(name: 'Trash', description: 'Papelera — listar, vaciar, eliminación permanente')]
#[OA\Tag(name: 'ShareLinks', description: 'Enlaces de compartir — crear, acceder, configurar')]
class Swagger {}
