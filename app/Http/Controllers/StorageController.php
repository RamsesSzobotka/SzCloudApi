<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storage\MoveItemRequest;
use App\Http\Requests\Storage\RenameRequest;
use App\Http\Requests\Storage\InitUploadRequest;
use App\Http\Requests\Storage\StoreFileRequest;
use App\Http\Requests\Storage\StoreFolderRequest;
use App\utils\LoggerHelper;
use App\utils\NameSanitizer;
use App\Dtos\FolderDto;
use App\utils\ExceptionCustom\StorageException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use App\Services\FileService;
use App\Services\FolderService;
use App\Services\StorageUsageService;
use App\utils\MinIOHelper;
use App\utils\Security;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StorageController extends Controller
{
    public function __construct(
        private FileService $fileService,
        private FolderService $folderService,
        private StorageUsageService $storageUsageService,
    ){}

    private function handleStorageErrors(callable $action){
        try{
            return $action();
        }catch(CarpetaEliminadaException $e){
            abort(400, $e->getMessage());
        }catch(CarpetaMovimientoPropioException $e){
            abort(400, $e->getMessage());
        }catch(CarpetaCicloException $e){
            abort(400, $e->getMessage());
        }catch(StorageException $e){
            LoggerHelper::error("StorageException: " . $e->getMessage());
            abort(400, $e->getMessage());
        }catch(ModelNotFoundException $e){
            abort(404, "El recurso solicitado no existe");
        }catch(ValidationException $e){
            abort(422, $e->getMessage());
        }catch(InvalidArgumentException $e){
            abort(400, $e->getMessage());
        }catch(HttpException $e){
            throw $e;
        }catch(Exception $e){
            LoggerHelper::exception($e);
        }
    }

    #[OA\Post(
        path: "/api/storage/upload/init",
        tags: ["Upload"],
        summary: "Iniciar subida multipart",
        description: "Crea una sesion de subida por chunks. Retorna el session_id, total_parts y chunk_size para proceder con la subida por partes.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["file_name", "mime_type", "total_size"],
                properties: [
                    new OA\Property(property: "file_name", type: "string", example: "documento.pdf", description: "Nombre original del archivo"),
                    new OA\Property(property: "mime_type", type: "string", example: "application/pdf", description: "Tipo MIME del archivo"),
                    new OA\Property(property: "total_size", type: "integer", example: 10485760, description: "Tamano total del archivo en bytes"),
                    new OA\Property(property: "folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Null para raiz."),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Sesion de subida creada", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "session_id", type: "string", format: "uuid", description: "ID de la sesion de subida"),
                    new OA\Property(property: "total_parts", type: "integer", description: "Numero total de chunks"),
                    new OA\Property(property: "chunk_size", type: "integer", description: "Tamano de cada chunk en bytes"),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 422, description: "Validacion fallida"),
        ]
    )]
    public function initUpload(InitUploadRequest $req){
        return $this->handleStorageErrors(function() use ($req){
            $fileName = $req->file_name;
            if (!NameSanitizer::isValid($fileName)) {
                abort(422, 'El nombre del archivo contiene caracteres no permitidos');
            }
            $user = Security::isOwner();
            if ($req->folder_id !== null) {
                $this->folderService->getFolder($user->id, $req->folder_id);
            }
            return response()->json($this->fileService->initMultipartUpload(
                $user, $req->file_name, $req->mime_type, $req->total_size, $req->folder_id
            ), 201);
        });
    }

    #[OA\Put(
        path: "/api/storage/upload/{session_id}/chunk",
        tags: ["Upload"],
        summary: "Subir chunk",
        description: "Envia un chunk del archivo. Cada chunk es un fragmento del archivo que se sube individualmente a S3 via multipart upload.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session_id", in: "path", required: true, description: "ID de la sesion de subida (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["chunk", "part_number", "total_parts"],
                    properties: [
                        new OA\Property(property: "chunk", type: "string", format: "binary", description: "Fragmento del archivo"),
                        new OA\Property(property: "part_number", type: "integer", example: 1, description: "Numero de parte (empezando en 1)"),
                        new OA\Property(property: "total_parts", type: "integer", example: 5, description: "Total de partes del archivo"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Chunk subido exitosamente", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "part_number", type: "integer", description: "Numero de parte subida"),
                    new OA\Property(property: "etag", type: "string", description: "ETag de la parte en S3"),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Sesion no encontrada"),
        ]
    )]
    public function uploadChunk(string $session_id, Request $req){
        return $this->handleStorageErrors(function() use ($session_id, $req){
            $user = Security::isOwner();
            $req->validate([
                'chunk' => ['required', 'file'],
                'part_number' => ['required', 'integer', 'min:1'],
                'total_parts' => ['required', 'integer', 'min:1'],
            ]);
            $resource = fopen($req->file('chunk')->getRealPath(), 'r');
            $result = $this->fileService->uploadChunk(
                $session_id, $req->part_number, $resource, $req->file('chunk')->getSize()
            );
            fclose($resource);
            return response()->json($result);
        });
    }

    #[OA\Post(
        path: "/api/storage/upload/{session_id}/complete",
        tags: ["Upload"],
        summary: "Completar subida",
        description: "Finaliza la subida multipart, ensambla las partes en S3 y crea el registro del archivo en la base de datos.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session_id", in: "path", required: true, description: "ID de la sesion de subida (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Usa el de la sesion si no se especifica."),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo creado exitosamente"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Sesion no encontrada"),
        ]
    )]
    public function completeUpload(string $session_id, Request $req){
        return $this->handleStorageErrors(function() use ($session_id, $req){
            $user = Security::isOwner();
            $folderId = $req->input('folder_id');
            return response()->json($this->fileService->completeMultipartUpload($session_id, $folderId));
        });
    }

    #[OA\Post(
        path: "/api/storage/upload/{session_id}/abort",
        tags: ["Upload"],
        summary: "Cancelar subida",
        description: "Cancela una subida en progreso y limpia los datos temporales en S3.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session_id", in: "path", required: true, description: "ID de la sesion de subida (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Subida cancelada", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Upload cancelado"),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Sesion no encontrada"),
        ]
    )]
    public function abortUpload(string $session_id){
        return $this->handleStorageErrors(function() use ($session_id){
            $user = Security::isOwner();
            $this->fileService->abortMultipartUpload($session_id);
            return response()->json(['message' => 'Upload cancelado']);
        });
    }

    #[OA\Post(
        path: "/api/storage/folder",
        tags: ["Folders"],
        summary: "Crear carpeta",
        description: "Crea una nueva carpeta en la ubicación especificada. Si ya existe una carpeta con el mismo nombre, retorna la existente sin crear duplicado. El nombre no puede contener los caracteres: / \\ : \" ' < > |. Se recomienda usar el endpoint check-name antes de crear para verificar conflictos.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Mis documentos", description: "Nombre de la carpeta. No puede contener: / \\ : \" ' < > |"),
                    new OA\Property(property: "parent_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta padre. Null para raíz."),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Carpeta creada (o existente si ya hay una con el mismo nombre)"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 422, description: "El nombre contiene caracteres no permitidos"),
        ]
    )]
    public function postFolder(StoreFolderRequest $req){
        return $this->handleStorageErrors(function() use ($req){
            if (!NameSanitizer::isValid($req->name)) {
                abort(422, 'El nombre contiene caracteres no permitidos');
            }
            $user = Security::isOwner();
            return $this->folderService->addFolder(new FolderDto($user->id, $req->name, $req->parent_id));
        });
    }

    #[OA\Post(
        path: "/api/storage/file",
        tags: ["Files"],
        summary: "Subir archivo",
        description: "Sube un nuevo archivo al almacenamiento. Si ya existe un archivo con el mismo nombre en la ubicación, se renombra automáticamente con sufijo (n). El nombre del archivo no puede contener los caracteres: / \\ : \" ' < > |. Se recomienda usar el endpoint check-name antes de subir para mostrar al usuario el nombre sugerido.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "Archivo a subir. El nombre no puede contener: / \\ : \" ' < > |"),
                        new OA\Property(property: "folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Null para raíz."),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Archivo subido"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 422, description: "El nombre del archivo contiene caracteres no permitidos"),
        ]
    )]
    public function postFile(StoreFileRequest $req){
        return $this->handleStorageErrors(function() use ($req){
            $fileName = $req->file('file')->getClientOriginalName();
            if (!NameSanitizer::isValid($fileName)) {
                abort(422, 'El nombre del archivo contiene caracteres no permitidos');
            }
            $user = Security::isOwner();
            if ($req->folder_id !== null) {
                $this->folderService->getFolder($user->id, $req->folder_id);
            }
            return response()->json($this->fileService->addFile($user, $req->file('file'), $req->folder_id),201);
        });
    }

    #[OA\Put(
        path: "/api/storage/file/{file_id}",
        tags: ["Files"],
        summary: "Reemplazar contenido de archivo",
        description: "Reemplaza el contenido del archivo subiendo una nueva version. La version anterior se preserva en el historial.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "Nuevo contenido del archivo"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo reemplazado, version anterior preservada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function replaceFile(string $file_id, StoreFileRequest $req){
        return $this->handleStorageErrors(function() use ($file_id, $req){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            return response()->json($this->fileService->replaceFile($file, $req->file('file')));
        });
    }

    #[OA\Get(
        path: "/api/storage/folder/content",
        tags: ["Folders"],
        summary: "Listar contenido de carpeta raíz",
        description: "Retorna el contenido de la carpeta raíz del usuario.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "per_page", in: "query", required: false, description: "Elementos por página", schema: new OA\Schema(type: "integer", default: 10)),
            new OA\Parameter(name: "page", in: "query", required: false, description: "Número de página", schema: new OA\Schema(type: "integer", default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Contenido de la carpeta raíz"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    #[OA\Get(
        path: "/api/storage/folder/content/{folder_Id}",
        tags: ["Folders"],
        summary: "Listar contenido de carpeta",
        description: "Retorna el contenido de una carpeta específica.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_Id", in: "path", required: true, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "per_page", in: "query", required: false, description: "Elementos por página", schema: new OA\Schema(type: "integer", default: 10)),
            new OA\Parameter(name: "page", in: "query", required: false, description: "Número de página", schema: new OA\Schema(type: "integer", default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Contenido de la carpeta"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function getFolderContent(Request $request, ?string $folder_Id = null){
        return $this->handleStorageErrors(function() use ($request, $folder_Id){
            $user = Security::isOwner();
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            return response()->json($this->folderService->getFolderContent($user->id, $folder_Id, $perPage, $page));
        });
    }

    #[OA\Get(
        path: "/api/storage/folder/{folder_Id}",
        tags: ["Folders"],
        summary: "Obtener info de carpeta",
        description: "Retorna los metadatos de una carpeta específica.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_Id", in: "path", required: false, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Metadatos de la carpeta"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function getFolderInfo(?string $folder_Id = null){
        return $this->handleStorageErrors(function() use ($folder_Id){
            $user = Security::isOwner();
            return response()->json($this->folderService->getFolder($user->id, $folder_Id));
        });
    }

    #[OA\Get(
        path: "/api/storage/file/{file_id}",
        tags: ["Files"],
        summary: "Obtener info de archivo",
        description: "Retorna los metadatos de un archivo específico.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Metadatos del archivo"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function getFile(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            return response()->json($this->fileService->getFile($user->id,$file_id));
        });
    }

    #[OA\Delete(
        path: "/api/storage/folder/{folder_id}",
        tags: ["Folders"],
        summary: "Eliminar carpeta",
        description: "Envía una carpeta a la papelera (soft delete).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "path", required: true, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Carpeta eliminada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function deleteFolder(string $folder_id){
        return $this->handleStorageErrors(function() use ($folder_id){
            $user = Security::isOwner();
            $folder = $this->folderService->getFolder($user->id, $folder_id);
            if ($folder->user_id !== $user->id) {
                return response()->json(['message' => 'Solo el propietario puede eliminar'], 403);
            }
            return response()->json($this->folderService->moveFolderToTrash($folder));
        });
    }

    #[OA\Delete(
        path: "/api/storage/file/{file_id}",
        tags: ["Files"],
        summary: "Eliminar archivo",
        description: "Envía un archivo a la papelera (soft delete).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Archivo eliminado"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function deleteFile(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            if ($file->user_id !== $user->id) {
                return response()->json(['message' => 'Solo el propietario puede eliminar'], 403);
            }
            return response()->json($this->fileService->moveFileToTrash($file));
        });
    }

    #[OA\Post(
        path: "/api/storage/folder/{folder_id}/restore",
        tags: ["Folders"],
        summary: "Restaurar carpeta",
        description: "Restaura una carpeta desde la papelera. Si ya existe una carpeta con el mismo nombre en el destino, se fusionan. Solo restaura la carpeta, sus hijos se mantienen en papelera hasta restaurar individualmente.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "path", required: true, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Carpeta restaurada"),
            new OA\Response(response: 400, description: "Carpeta no está en papelera"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function restoreFolder(string $folder_id){
        return $this->handleStorageErrors(function() use ($folder_id){
            $user = Security::isOwner();
            return response()->json(
                $this->folderService->restoreFolder(
                    $this->folderService->getTrashedFolder($user->id,$folder_id)
                )
            );
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/restore",
        tags: ["Files"],
        summary: "Restaurar archivo",
        description: "Restaura un archivo desde la papelera. Si su carpeta padre está en papelera, se restaura automáticamente.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "overwrite", type: "boolean", description: "Control de conflicto de nombres. Si es true y existe un archivo con el mismo nombre en la carpeta destino, lo reemplaza (preservando historial de versiones). Si es false o no se envía, renombra automáticamente el archivo restaurado con sufijo (n).", default: false),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo restaurado (o reemplazado si overwrite=true)"),
            new OA\Response(response: 400, description: "Archivo no está en papelera"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreFile(string $file_id, Request $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
            $user = Security::isOwner();
            return response()->json(
                $this->fileService->restoreFile(
                    $this->fileService->getTrashedFile($user->id,$file_id),
                    $request->boolean('overwrite', false)
                )
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/file/{file_id}/move",
        tags: ["Files"],
        summary: "Mover archivo",
        description: "Mueve un archivo a otra carpeta. Si ya existe un archivo con el mismo nombre en el destino, se renombra automáticamente con sufijo (n). Se recomienda usar check-name antes de mover para verificar conflictos.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo a mover (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["destination_folder_id"],
                properties: [
                    new OA\Property(property: "destination_folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Null para raíz."),
                    new OA\Property(property: "overwrite", type: "boolean", description: "Control de conflicto de nombres. Si es true y existe un archivo con el mismo nombre en la carpeta destino, lo reemplaza (preservando historial de versiones). Si es false o no se envía, renombra automáticamente el archivo con sufijo (n).", default: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo movido"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function moveFile(string $file_id, MoveItemRequest $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
            $user = Security::isOwner();

            $file = $this->fileService->getFile($user->id, $file_id);

            if ($request->boolean('overwrite')) {
                $conflict = \App\Models\File::where("user_id", $user->id)
                    ->where("folder_id", $request->destination_folder_id)
                    ->where("original_name", $file->original_name)
                    ->where("id", "!=", $file->id)
                    ->first();

                if ($conflict) {
                    $this->fileService->replaceFileFromStorage($conflict, $file);
                    $this->fileService->deletePermanentFile($file);
                    return response()->json($conflict);
                }
            }

            return response()->json(
                $this->fileService->moveFile(
                    $file,
                    $request->destination_folder_id
                )
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/folder/{folder_id?}/move",
        tags: ["Folders"],
        summary: "Mover carpeta",
        description: "Mueve una carpeta a otra ubicación. Si ya existe una carpeta con el mismo nombre en el destino, se fusionan automáticamente: el contenido de la carpeta con menos elementos se migra a la existente.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "path", required: true, description: "ID de la carpeta a mover (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["destination_folder_id"],
                properties: [
                    new OA\Property(property: "destination_folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Null para raíz."),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Carpeta movida"),
            new OA\Response(response: 400, description: "Error de movimiento (ciclo, origen = destino)"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function moveFolder(string $folder_id, MoveItemRequest $request){
        return $this->handleStorageErrors(function() use ($folder_id, $request){
            $user = Security::isOwner();

            $folder = $this->folderService->getFolder(
                $user->id,
                $folder_id
            );

            return response()->json(
                $this->folderService->moveFolder(
                    $folder,
                    $request->destination_folder_id
                )
            );
        });
    }

    #[OA\Get(
        path: "/api/storage/folders/hierarchy",
        tags: ["Folders"],
        summary: "Jerarquía de carpetas",
        description: "Retorna todas las carpetas del usuario en estructura de árbol. Solo carpetas, sin archivos.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Árbol de carpetas"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function getFolderHierarchy(){
        return $this->handleStorageErrors(function(){
            $user = Security::isOwner();
            return response()->json(
                $this->folderService->getFolderHierarchy($user->id)
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/file/{file_id}/rename",
        tags: ["Files"],
        summary: "Renombrar archivo",
        description: "Cambia el nombre de un archivo existente. Si ya existe un archivo con el mismo nombre, se agrega sufijo (n) automáticamente. El nombre no puede contener los caracteres: / \\ : \" ' < > |.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "nuevo_nombre.pdf", description: "Nuevo nombre. No puede contener: / \\ : \" ' < > |"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo renombrado"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
            new OA\Response(response: 422, description: "El nombre contiene caracteres no permitidos"),
        ]
    )]
    public function renameFile(string $file_id, RenameRequest $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
            if (!NameSanitizer::isValid($request->name)) {
                abort(422, 'El nombre contiene caracteres no permitidos');
            }
            $user = Security::isOwner();

            $file = $this->fileService->getFile($user->id, $file_id);

            return response()->json(
                $this->fileService->renameFile($file, $request->name)
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/folder/{folder_id}/rename",
        tags: ["Folders"],
        summary: "Renombrar carpeta",
        description: "Cambia el nombre de una carpeta existente. Si ya existe una carpeta con el mismo nombre, se fusionan: el contenido de la carpeta con menos elementos se migra a la existente. El nombre no puede contener los caracteres: / \\ : \" ' < > |.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "path", required: true, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Nuevo nombre", description: "Nuevo nombre. No puede contener: / \\ : \" ' < > |"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Carpeta renombrada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
            new OA\Response(response: 422, description: "El nombre contiene caracteres no permitidos"),
        ]
    )]
    public function renameFolder(string $folder_id, RenameRequest $request){
        return $this->handleStorageErrors(function() use ($folder_id, $request){
            if (!NameSanitizer::isValid($request->name)) {
                abort(422, 'El nombre contiene caracteres no permitidos');
            }
            $user = Security::isOwner();

            $folder = $this->folderService->getFolder($user->id, $folder_id);

            return response()->json(
                $this->folderService->renameFolder($folder, $request->name)
            );
        });
    }

    #[OA\Delete(
        path: "/api/storage/trash",
        tags: ["Trash"],
        summary: "Vaciar papelera",
        description: "Elimina permanentemente todos los elementos de la papelera.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Papelera vaciada"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function deleteTrash(){
        return $this->handleStorageErrors(function (){
            $user = Security::isOwner();
            return response()->json(
                $this->folderService->deleteTrash($user->id)
            );
        });
    }

    #[OA\Delete(
        path: "/api/storage/trash/{id}/permanent",
        tags: ["Trash"],
        summary: "Eliminación permanente",
        description: "Elimina permanentemente un elemento específico de la papelera.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID del elemento (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "type", type: "string", enum: ["folder", "file"], default: "folder", description: "Tipo de elemento a eliminar"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Elemento eliminado permanentemente"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Elemento no encontrado"),
        ]
    )]
    public function deletePermanent(string $id, Request $req){
        return $this->handleStorageErrors(function () use ($id, $req){
            $user = Security::isOwner();
            $type = $req->input("type", "folder");

            if ($type === "folder") {
                $folder = $this->folderService->getTrashedFolder($user->id, $id);
                return response()->json(
                    $this->folderService->deletePermanentFolderWithTransaction($folder)
                );
            }

            $file = $this->fileService->getTrashedFile($user->id, $id);
            return response()->json(
                $this->fileService->deletePermanentFile($file)
            );
        });
    }

    #[OA\Get(
        path: "/api/storage/trash",
        tags: ["Trash"],
        summary: "Listar papelera",
        description: "Retorna todos los elementos en la papelera.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Lista de elementos en papelera"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function getTrash(){
        return $this->handleStorageErrors(function (){
            $user = Security::isOwner();
            return response()->json(
                $this->folderService->getTrash($user->id)
            );
        });
    }

    #[OA\Get(
        path: "/api/storage/file/{file_id}/download",
        tags: ["Files"],
        summary: "Descargar archivo",
        description: "Genera y retorna una URL temporaria (30 min) para descargar el archivo desde S3.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo a descargar", schema: new OA\Schema(type: "string", format: "uuid"))
        ],
        responses: [
            new OA\Response(response: 200, description: "URL de descarga generada", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "url", type: "string", format: "uri", description: "URL temporaria de descarga (válida por 30 minutos)")
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function download(string $id){
        return $this->handleStorageErrors(function () use ($id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id,$id);

            return response()->json([
                "url" => MinIOHelper::urlDownloadFile($file)
            ]);
        });
    }

    #[OA\Get(
        path: "/api/storage/file/{file_id}/versions",
        tags: ["Files"],
        summary: "Obtener versiones de un archivo",
        description: "Retorna la lista de versiones de un archivo ordenadas de la más reciente a la más antigua.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Lista de versiones"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function getVersions(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            return response()->json($this->fileService->getVersionsInfo($file));
        });
    }

    #[OA\Get(
        path: "/api/storage/file/{file_id}/versions/check",
        tags: ["Files"],
        summary: "Verificar disponibilidad de versiones",
        description: "Indica si el archivo tiene versiones anteriores o posteriores a la actual.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Info de versiones disponibles", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "has_older", type: "boolean", description: "Si existen versiones anteriores"),
                    new OA\Property(property: "has_newer", type: "boolean", description: "Si existen versiones posteriores"),
                    new OA\Property(property: "current_version", type: "integer", description: "Número de versión actual"),
                    new OA\Property(property: "total_versions", type: "integer", description: "Total de versiones almacenadas"),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function checkVersions(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            return response()->json($this->fileService->hasVersionsInfo($file));
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/versions/restore-back",
        tags: ["Files"],
        summary: "Restaurar versión anterior",
        description: "Restaura el archivo a su versión anterior (Ctrl+Z).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Archivo restaurado a versión anterior"),
            new OA\Response(response: 400, description: "No hay versión anterior disponible"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreBackVersion(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            $result = $this->fileService->restoreBackVersion($file);

            if(!$result){
                abort(400, "No hay versión anterior disponible");
            }

            return response()->json($file);
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/versions/restore-front",
        tags: ["Files"],
        summary: "Restaurar versión posterior",
        description: "Restaura el archivo a su versión posterior (Ctrl+Shift+Z).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Archivo restaurado a versión posterior"),
            new OA\Response(response: 400, description: "No hay versión posterior disponible"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreFrontVersion(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            $result = $this->fileService->restoreFrontVersion($file);

            if(!$result){
                abort(400, "No hay versión posterior disponible");
            }

            return response()->json($file);
        });
    }

    #[OA\Get(
        path: "/api/storage/file/{file_id}/activity",
        tags: ["Files"],
        summary: "Historial de actividad del archivo",
        description: "Retorna el registro de todos los cambios realizados al archivo (renombres, movimientos, cambios de contenido, etc).",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Registro de actividad"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function getActivity(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            return response()->json($this->fileService->getActivityLog($file));
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/activity/restore-back",
        tags: ["Files"],
        summary: "Deshacer última acción",
        description: "Deshace el último cambio registrado en la actividad (renombre, movimiento, etc). Equivale a Ctrl+Z sobre acciones.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Acción deshecha"),
            new OA\Response(response: 400, description: "No hay acción para deshacer"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreBackActivity(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            $result = $this->fileService->restoreBackActivity($file);

            if(!$result){
                abort(400, "No hay acción para deshacer");
            }

            return response()->json($file);
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/activity/restore-front",
        tags: ["Files"],
        summary: "Rehacer última acción deshecha",
        description: "Rehace el último cambio que fue deshecho. Equivale a Ctrl+Shift+Z sobre acciones.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Acción rehecha"),
            new OA\Response(response: 400, description: "No hay acción para rehacer"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreFrontActivity(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $file_id);
            $result = $this->fileService->restoreFrontActivity($file);

            if(!$result){
                abort(400, "No hay acción para rehacer");
            }

            return response()->json($file);
        });
    }

    #[OA\Get(
        path: "/api/storage/folder/check-name",
        tags: ["Folders"],
        summary: "Verificar si existe una carpeta con el mismo nombre",
        description: "Verifica si ya existe una carpeta con el mismo nombre en la ubicación. Si existe, retorna info del conflicto para posible fusión. Se recomienda usar este endpoint antes de crear, mover o renombrar carpetas.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "parent_id", in: "query", required: false, description: "ID de la carpeta padre (UUID). Null para raíz.", schema: new OA\Schema(type: "string", format: "uuid", nullable: true)),
            new OA\Parameter(name: "name", in: "query", required: true, description: "Nombre de la carpeta a verificar.", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Resultado de verificación", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "exists", type: "boolean", description: "Si existe una carpeta con ese nombre"),
                    new OA\Property(property: "conflicting_folder", type: "object", nullable: true, description: "Info de la carpeta conflictiva", properties: [
                        new OA\Property(property: "id", type: "string", format: "uuid"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "content_count", type: "integer", description: "Cantidad de elementos directos (carpetas + archivos)"),
                    ]),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function checkFolderName(Request $request){
        return $this->handleStorageErrors(function() use ($request){
            $user = Security::isOwner();
            $request->validate(["name" => "required|string|max:255"]);

            return response()->json(
                $this->folderService->checkFolderName(
                    $user->id,
                    $request->input("parent_id"),
                    $request->name
                )
            );
        });
    }

    #[OA\Get(
        path: "/api/storage/file/check-name",
        tags: ["Files"],
        summary: "Verificar si existe un archivo con el mismo nombre",
        description: "Verifica si ya existe un archivo con el mismo nombre en una carpeta. Si existe, retorna el nombre sugerido con sufijo (n). Se recomienda usar este endpoint antes de mover o renombrar archivos.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "query", required: false, description: "ID de la carpeta (UUID). Null para raíz.", schema: new OA\Schema(type: "string", format: "uuid", nullable: true)),
            new OA\Parameter(name: "name", in: "query", required: true, description: "Nombre del archivo a verificar.", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Resultado de verificación", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "exists", type: "boolean", description: "Si existe un archivo con ese nombre"),
                    new OA\Property(property: "suggested_name", type: "string", nullable: true, description: "Nombre sugerido si hay conflicto"),
                ]
            )),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function checkFileName(Request $request){
        return $this->handleStorageErrors(function() use ($request){
            $user = Security::isOwner();
            $request->validate(["name" => "required|string|max:255"]);

            return response()->json(
                $this->fileService->checkFileName(
                    $user->id,
                    $request->input("folder_id"),
                    $request->name
                )
            );
        });
    }

    #[OA\Get(
        path: "/api/storage/info",
        tags: ["Storage"],
        summary: "Obtener info de almacenamiento y plan",
        description: "Retorna el uso de almacenamiento y el plan actual del usuario.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Info de almacenamiento"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function getStorageInfo(){
        $user = Security::isOwner();
        return response()->json(
            $this->storageUsageService->getStorageInfo($user)
        );
    }

    #[OA\Post(
        path: "/api/storage/verify",
        tags: ["Storage"],
        summary: "Verificar si cabe un archivo",
        description: "Verifica si el usuario tiene espacio suficiente para un archivo del tamaño indicado.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["file_size"],
                properties: [
                    new OA\Property(property: "file_size", type: "integer", description: "Tamaño del archivo en bytes"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Resultado de verificación"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function storageVerify(Request $request){
        $user = Security::isOwner();
        $request->validate(["file_size" => "required|integer|min:1"]);

        return response()->json([
            "allowed" => $this->storageUsageService->storageVerify($user, $request->file_size),
        ]);
    }
}