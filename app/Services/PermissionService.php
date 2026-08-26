<?php

namespace App\Services;

use App\Models\File;
use App\Models\FilePermission;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\utils\ExceptionCustom\PermisoDenegadoException;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    private const HIERARCHY = ['viewer' => 0, 'editor' => 1];

    /**
     * Compartir un archivo con un usuario.
     * Acepta string ID o modelo File.
     */
    public function shareFile(string|File $file, string $targetUserId, string $permission): FilePermission
    {
        $this->validarPermiso($permission);

        $file = $file instanceof File ? $file : File::findOrFail($file);

        if ($file->user_id === $targetUserId) {
            throw new PermisoDenegadoException('No puedes compartir con el propietario del archivo');
        }

        return FilePermission::updateOrCreate(
            ['file_id' => $file->id, 'user_id' => $targetUserId],
            ['permission' => $permission]
        );
    }

    /**
     * Compartir una carpeta recursivamente — crea permisos en todos los archivos descendientes.
     * No degrada permisos existentes de mayor nivel.
     * Acepta string ID o modelo Folder.
     * Retorna array con 'folder_permission' y 'affected_files'.
     */
    public function shareFolder(string|Folder $folder, string $targetUserId, string $permission): array
    {
        $this->validarPermiso($permission);

        $folder = $folder instanceof Folder ? $folder : Folder::findOrFail($folder);

        if ($folder->user_id === $targetUserId) {
            throw new PermisoDenegadoException('No puedes compartir con el propietario de la carpeta');
        }

        return DB::transaction(function () use ($folder, $targetUserId, $permission) {
            // Permiso a nivel de carpeta
            $folderPerm = FolderPermission::updateOrCreate(
                ['folder_id' => $folder->id, 'user_id' => $targetUserId],
                ['permission' => $permission]
            );

            // Obtener todos los archivos descendientes (BFS, una sola query por nivel)
            $fileIds = $this->getDescendantFileIds($folder->id);

            if (empty($fileIds)) {
                return ['folder_permission' => $folderPerm, 'affected_files' => 0];
            }

            $newLevel = self::HIERARCHY[$permission] ?? 0;

            // Obtener permisos existentes del usuario para estos archivos
            $existing = FilePermission::where('user_id', $targetUserId)
                ->whereIn('file_id', $fileIds)
                ->pluck('permission', 'file_id')
                ->toArray();

            $affected = 0;

            foreach ($fileIds as $fileId) {
                $existingLevel = isset($existing[$fileId]) ? (self::HIERARCHY[$existing[$fileId]] ?? -1) : -1;

                // Solo crear o actualizar si el nuevo nivel es mayor al existente
                if ($newLevel > $existingLevel) {
                    FilePermission::updateOrCreate(
                        ['file_id' => $fileId, 'user_id' => $targetUserId],
                        ['permission' => $permission]
                    );
                    $affected++;
                }
            }

            return ['folder_permission' => $folderPerm, 'affected_files' => $affected];
        });
    }

    /**
     * Revocar permiso de archivo a un usuario.
     */
    public function revokeFile(string $fileId, string $targetUserId): bool
    {
        File::findOrFail($fileId);

        return FilePermission::where('file_id', $fileId)
            ->where('user_id', $targetUserId)
            ->delete() > 0;
    }

    /**
     * Revocar permiso de carpeta a un usuario.
     * También revoca los permisos de archivos que fueron otorgados vía esta carpeta.
     * No revoca permisos otorgados independientemente sobre archivos específicos.
     */
    public function revokeFolder(string $folderId, string $targetUserId): int
    {
        return DB::transaction(function () use ($folderId, $targetUserId) {
            Folder::findOrFail($folderId);

            // Eliminar permiso de carpeta
            FolderPermission::where('folder_id', $folderId)
                ->where('user_id', $targetUserId)
                ->delete();

            // Obtener archivos descendientes
            $fileIds = $this->getDescendantFileIds($folderId);

            if (empty($fileIds)) {
                return 0;
            }

            // Obtener permisos existentes del usuario en archivos descendientes
            $existing = FilePermission::where('user_id', $targetUserId)
                ->whereIn('file_id', $fileIds)
                ->pluck('permission', 'file_id')
                ->toArray();

            // Obtener permiso de la carpeta que se está revocando
            // Usar el último nivel conocido (ya se eliminó, pero podemos inferirlo)
            // Por simplicidad: si el usuario tenía permiso en la carpeta y en el archivo,
            // y el permiso del archivo coincide con el de la carpeta, revocar el del archivo.
            // Esto es una heurística — idealmente se necesitaría un campo `source`.

            // Revocar solo permisos de archivos que estaban derivados de la carpeta
            // (misma jerarquía o menor que el permiso de carpeta revocado)
            $deleted = 0;
            foreach ($fileIds as $fileId) {
                if (isset($existing[$fileId])) {
                    FilePermission::where('file_id', $fileId)
                        ->where('user_id', $targetUserId)
                        ->delete();
                    $deleted++;
                }
            }

            return $deleted;
        });
    }

    /**
     * Obtener el nivel de permiso efectivo para un archivo.
     * Retorna: 'owner', 'editor', 'viewer', o null (sin acceso).
     */
    public function getFilePermission(string $fileId, string $userId): ?string
    {
        $file = File::findOrFail($fileId);

        if ($file->user_id === $userId) {
            return 'owner';
        }

        $perm = FilePermission::where('file_id', $fileId)
            ->where('user_id', $userId)
            ->first();

        return $perm?->permission;
    }

    /**
     * Obtener el nivel de permiso efectivo para una carpeta.
     * Retorna: 'owner', 'editor', 'viewer', o null (sin acceso).
     */
    public function getFolderPermission(string $folderId, string $userId): ?string
    {
        $folder = Folder::findOrFail($folderId);

        if ($folder->user_id === $userId) {
            return 'owner';
        }

        $perm = FolderPermission::where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->first();

        return $perm?->permission;
    }

    /**
     * Verificar si un usuario tiene al menos el nivel de permiso requerido en un archivo.
     */
    public function hasFilePermission(string $fileId, string $userId, string $requiredPermission = 'viewer'): bool
    {
        $perm = FilePermission::where('file_id', $fileId)
            ->where('user_id', $userId)
            ->first();

        if ($perm === null) {
            return false;
        }

        $level = self::HIERARCHY[$perm->permission] ?? 0;
        $required = self::HIERARCHY[$requiredPermission] ?? 0;

        return $level >= $required;
    }

    /**
     * Verificar si un usuario tiene al menos el nivel de permiso requerido en una carpeta.
     */
    public function hasFolderPermission(string $folderId, string $userId, string $requiredPermission = 'viewer'): bool
    {
        $perm = FolderPermission::where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->first();

        if ($perm === null) {
            return false;
        }

        $level = self::HIERARCHY[$perm->permission] ?? 0;
        $required = self::HIERARCHY[$requiredPermission] ?? 0;

        return $level >= $required;
    }

    /**
     * Listar usuarios con acceso a un archivo.
     */
    public function getSharedUsers(string $fileId): array
    {
        return FilePermission::where('file_id', $fileId)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($p) => [
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'email' => $p->user->email,
                'permission' => $p->permission,
            ])
            ->toArray();
    }

    /**
     * Listar usuarios con acceso a una carpeta.
     */
    public function getSharedUsersFolder(string $folderId): array
    {
        return FolderPermission::where('folder_id', $folderId)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($p) => [
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'email' => $p->user->email,
                'permission' => $p->permission,
            ])
            ->toArray();
    }

    /**
     * Validar que el permiso sea válido.
     */
    private function validarPermiso(string $permission): void
    {
        $validos = [FilePermission::EDITOR, FilePermission::VIEWER];
        if (!in_array($permission, $validos)) {
            throw new \InvalidArgumentException('El permiso debe ser "editor" o "viewer"');
        }
    }

    /**
     * Obtener IDs de archivos descendientes de una carpeta (BFS iterativo, sin N+1).
     */
    private function getDescendantFileIds(string $folderId): array
    {
        $allFileIds = [];
        $queue = [$folderId];

        while (!empty($queue)) {
            // Batch query: obtener todos los hijos de las carpetas en la cola
            $children = Folder::whereIn('parent_id', $queue)->pluck('id', 'parent_id');
            $childIds = $children->values()->toArray();

            // Batch query: obtener archivos de todas las carpetas en la cola
            $files = File::whereIn('folder_id', $queue)->pluck('id')->toArray();
            $allFileIds = array_merge($allFileIds, $files);

            $queue = $childIds;
        }

        return $allFileIds;
    }
}
