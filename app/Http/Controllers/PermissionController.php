<?php

namespace App\Http\Controllers;

use App\Http\Requests\Permission\StoreFilePermissionRequest;
use App\Http\Requests\Permission\StoreFolderPermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Models\File;
use App\Models\Folder;
use App\Services\PermissionService;
use App\utils\ExceptionCustom\PermisoDenegadoException;
use App\utils\HttpError;
use App\utils\Security;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class PermissionController extends Controller
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    private function handlePermissionErrors(callable $action)
    {
        try {
            return $action();
        } catch (PermisoDenegadoException $e) {
            abort(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            abort(404, 'El recurso solicitado no existe');
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(400, $e->getMessage());
        } catch (\Exception $e) {
            HttpError::InternalError($e);
        }
    }

    // ─── Permisos de Archivo ─────────────────────────────────

    #[OA\Post(
        path: '/api/storage/file/{fileId}/permissions',
        tags: ['Permissions'],
        summary: 'Compartir archivo con usuario',
        description: 'Otorga permiso de acceso a un archivo para otro usuario. Solo el propietario del archivo puede compartirlo. Niveles disponibles: "editor" (puede descargar, renombrar, mover) y "viewer" (solo puede descargar y ver metadata).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fileId', in: 'path', required: true, description: 'ID del archivo (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'permission'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid', description: 'ID del usuario con quien se comparte.'),
                    new OA\Property(property: 'permission', type: 'string', enum: ['editor', 'viewer'], description: 'Nivel de permiso a otorgar.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permiso otorgado correctamente'),
            new OA\Response(response: 400, description: 'Solicitud inválida'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Solo el propietario puede gestionar permisos'),
            new OA\Response(response: 404, description: 'Archivo no encontrado'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function storeFilePermission(StoreFilePermissionRequest $request, string $fileId)
    {
        return $this->handlePermissionErrors(function () use ($request, $fileId) {
            $user = Security::isOwner();
            $file = $this->requireFile($fileId, $user->id);

            $perm = $this->permissionService->shareFile($file, $request->user_id, $request->permission);

            return response()->json([
                'message' => 'Permiso otorgado',
                'permission' => $perm,
            ], 201);
        });
    }

    #[OA\Get(
        path: '/api/storage/file/{fileId}/permissions',
        tags: ['Permissions'],
        summary: 'Listar permisos de un archivo',
        description: 'Retorna la lista de usuarios que tienen acceso a un archivo y su nivel de permiso. Solo el propietario y los usuarios con acceso pueden consultar esta información.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fileId', in: 'path', required: true, description: 'ID del archivo (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de permisos'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No tienes acceso a este archivo'),
            new OA\Response(response: 404, description: 'Archivo no encontrado'),
        ]
    )]
    public function getFilePermissions(string $fileId)
    {
        return $this->handlePermissionErrors(function () use ($fileId) {
            $user = Security::isOwner();
            $this->requireFileAccess($fileId, $user->id);

            return response()->json([
                'permissions' => $this->permissionService->getSharedUsers($fileId),
            ]);
        });
    }

    #[OA\Patch(
        path: '/api/storage/file/{fileId}/permissions/{userId}',
        tags: ['Permissions'],
        summary: 'Cambiar nivel de permiso de un archivo',
        description: 'Modifica el nivel de permiso de un usuario sobre un archivo. Solo el propietario puede realizar esta acción. El nivel puede ser "editor" o "viewer".',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fileId', in: 'path', required: true, description: 'ID del archivo (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'ID del usuario cuyo permiso se modifica (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permission'],
                properties: [
                    new OA\Property(property: 'permission', type: 'string', enum: ['editor', 'viewer'], description: 'Nuevo nivel de permiso.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permiso actualizado'),
            new OA\Response(response: 400, description: 'Solicitud inválida'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Solo el propietario puede gestionar permisos'),
            new OA\Response(response: 404, description: 'Archivo no encontrado'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function updateFilePermission(UpdatePermissionRequest $request, string $fileId, string $userId)
    {
        return $this->handlePermissionErrors(function () use ($request, $fileId, $userId) {
            $user = Security::isOwner();
            $file = $this->requireFile($fileId, $user->id);

            $perm = $this->permissionService->shareFile($file, $userId, $request->permission);

            return response()->json([
                'message' => 'Permiso actualizado',
                'permission' => $perm,
            ]);
        });
    }

    #[OA\Delete(
        path: '/api/storage/file/{fileId}/permissions/{userId}',
        tags: ['Permissions'],
        summary: 'Revocar acceso a un archivo',
        description: 'Elimina el permiso de un usuario sobre un archivo. Solo el propietario puede revocar accesos. Una vez revocado, el usuario ya no podrá acceder al archivo.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fileId', in: 'path', required: true, description: 'ID del archivo (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'ID del usuario al que se le revoca el acceso (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permiso revocado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Solo el propietario puede gestionar permisos'),
            new OA\Response(response: 404, description: 'Archivo no encontrado'),
        ]
    )]
    public function revokeFilePermission(string $fileId, string $userId)
    {
        return $this->handlePermissionErrors(function () use ($fileId, $userId) {
            $user = Security::isOwner();
            $this->requireFile($fileId, $user->id);

            $this->permissionService->revokeFile($fileId, $userId);

            return response()->json(['message' => 'Permiso revocado']);
        });
    }

    // ─── Permisos de Carpeta ─────────────────────────────────

    #[OA\Post(
        path: '/api/storage/folder/{folderId}/permissions',
        tags: ['Permissions'],
        summary: 'Compartir carpeta con usuario',
        description: 'Otorga permiso de acceso a una carpeta y recursivamente a todos los archivos que contenga. Solo el propietario de la carpeta puede compartirla. Al compartir una carpeta con nivel "editor", el usuario puede crear, renombrar, mover y eliminar archivos dentro de ella, pero no puede compartir ni eliminar la carpeta raíz. Con nivel "viewer", solo puede descargar y ver metadata. Si un archivo ya tiene un permiso de mayor nivel, no se degrada.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'folderId', in: 'path', required: true, description: 'ID de la carpeta (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'permission'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid', description: 'ID del usuario con quien se comparte.'),
                    new OA\Property(property: 'permission', type: 'string', enum: ['editor', 'viewer'], description: 'Nivel de permiso a otorgar.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permiso otorgado a carpeta y archivos descendientes'),
            new OA\Response(response: 400, description: 'Solicitud inválida'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Solo el propietario puede gestionar permisos'),
            new OA\Response(response: 404, description: 'Carpeta no encontrada'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function storeFolderPermission(StoreFolderPermissionRequest $request, string $folderId)
    {
        return $this->handlePermissionErrors(function () use ($request, $folderId) {
            $user = Security::isOwner();
            $folder = $this->requireFolder($folderId, $user->id);

            $perm = $this->permissionService->shareFolder($folder, $request->user_id, $request->permission);

            return response()->json([
                'message' => "Permiso otorgado a carpeta y {$perm['affected_files']} archivos",
                'permission' => $perm['folder_permission'],
                'affected_files' => $perm['affected_files'],
            ], 201);
        });
    }

    #[OA\Get(
        path: '/api/storage/folder/{folderId}/permissions',
        tags: ['Permissions'],
        summary: 'Listar permisos de una carpeta',
        description: 'Retorna la lista de usuarios que tienen acceso a una carpeta y su nivel de permiso. Solo el propietario y los usuarios con acceso pueden consultar esta información.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'folderId', in: 'path', required: true, description: 'ID de la carpeta (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de permisos'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No tienes acceso a esta carpeta'),
            new OA\Response(response: 404, description: 'Carpeta no encontrada'),
        ]
    )]
    public function getFolderPermissions(string $folderId)
    {
        return $this->handlePermissionErrors(function () use ($folderId) {
            $user = Security::isOwner();
            $this->requireFolderAccess($folderId, $user->id);

            return response()->json([
                'permissions' => $this->permissionService->getSharedUsersFolder($folderId),
            ]);
        });
    }

    #[OA\Delete(
        path: '/api/storage/folder/{folderId}/permissions/{userId}',
        tags: ['Permissions'],
        summary: 'Revocar acceso a una carpeta',
        description: 'Elimina el permiso de un usuario sobre una carpeta y recursivamente sobre todos los archivos descendientes. Solo el propietario puede revocar accesos.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'folderId', in: 'path', required: true, description: 'ID de la carpeta (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'ID del usuario al que se le revoca el acceso (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permiso revocado de carpeta y todos los archivos'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Solo el propietario puede gestionar permisos'),
            new OA\Response(response: 404, description: 'Carpeta no encontrada'),
        ]
    )]
    public function revokeFolderPermission(string $folderId, string $userId)
    {
        return $this->handlePermissionErrors(function () use ($folderId, $userId) {
            $user = Security::isOwner();
            $this->requireFolder($folderId, $user->id);

            $this->permissionService->revokeFolder($folderId, $userId);

            return response()->json(['message' => 'Permiso revocado de carpeta y todos los archivos']);
        });
    }

    // ─── Helpers privados ────────────────────────────────────

    /**
     * Carga un archivo y verifica que el usuario sea el propietario.
     * Retorna el modelo File para reutilizarlo.
     */
    private function requireFile(string $fileId, string $userId): File
    {
        $file = File::findOrFail($fileId);
        if ($file->user_id !== $userId) {
            abort(403, 'Solo el propietario del archivo puede gestionar permisos');
        }
        return $file;
    }

    /**
     * Carga un archivo y verifica que el usuario tenga acceso.
     */
    private function requireFileAccess(string $fileId, string $userId): void
    {
        $perm = $this->permissionService->getFilePermission($fileId, $userId);
        if ($perm === null) {
            abort(403, 'No tienes acceso a este archivo');
        }
    }

    /**
     * Carga una carpeta y verifica que el usuario sea el propietario.
     * Retorna el modelo Folder para reutilizarlo.
     */
    private function requireFolder(string $folderId, string $userId): Folder
    {
        $folder = Folder::findOrFail($folderId);
        if ($folder->user_id !== $userId) {
            abort(403, 'Solo el propietario de la carpeta puede gestionar permisos');
        }
        return $folder;
    }

    /**
     * Carga una carpeta y verifica que el usuario tenga acceso.
     */
    private function requireFolderAccess(string $folderId, string $userId): void
    {
        $perm = $this->permissionService->getFolderPermission($folderId, $userId);
        if ($perm === null) {
            abort(403, 'No tienes acceso a esta carpeta');
        }
    }
}
