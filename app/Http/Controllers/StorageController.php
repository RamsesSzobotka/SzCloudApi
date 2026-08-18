<?php

namespace App\Http\Controllers;

use App\Http\Requests\Storage\MoveItemRequest;
use App\Http\Requests\Storage\RenameRequest;
use App\Http\Requests\Storage\StoreFileRequest;
use App\Http\Requests\Storage\StoreFolderRequest;
use App\utils\HttpError;
use App\utils\LoggerHelper;
use App\Dtos\FolderDto;
use App\utils\ExceptionCustom\StorageException;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use App\Services\StorageService;
use App\utils\Security;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Exception;

class StorageController extends Controller
{
    public function __construct(
        private StorageService $storageService   
    ){}

    private function handleStorageErrors(callable $action){
        try{
            return $action();
        }catch(NombreDuplicadoException $e){
            abort(409, $e->getMessage());
        }catch(CarpetaEliminadaException $e){
            abort(400, $e->getMessage());
        }catch(CarpetaMovimientoPropioException $e){
            abort(400, $e->getMessage());
        }catch(CarpetaCicloException $e){
            abort(400, $e->getMessage());
        }catch(StorageException $e){
            LoggerHelper::error("StorageException: " . $e->getMessage());
            abort(400, "No se pudo completar la operación");
        }catch(ModelNotFoundException $e){
            abort(404, "El recurso solicitado no existe");
        }catch(ValidationException $e){
            abort(422, $e->getMessage());
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    #[OA\Post(
        path: "/api/storage/folder",
        tags: ["Folders"],
        summary: "Crear carpeta",
        description: "Crea una nueva carpeta en la ubicación especificada.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Mis documentos"),
                    new OA\Property(property: "parent_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta padre. Null para raíz."),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Carpeta creada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 409, description: "Nombre duplicado"),
        ]
    )]
    public function postFolder(StoreFolderRequest $req){
        return $this->handleStorageErrors(function() use ($req){
            $user = Security::isOwner();
            return $this->storageService->addFolder(new FolderDto($user->id, $req->name, $req->parent_id));
        });
    }

    #[OA\Post(
        path: "/api/storage/file",
        tags: ["Files"],
        summary: "Subir archivo",
        description: "Sube un nuevo archivo al almacenamiento.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "Archivo a subir"),
                        new OA\Property(property: "folder_id", type: "string", format: "uuid", nullable: true, description: "ID de la carpeta destino. Null para raíz."),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Archivo subido"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function postFile(StoreFileRequest $req){
        return $this->handleStorageErrors(function() use ($req){
            $user = Security::isOwner();
            return response()->json($this->storageService->addFile($user->id, $req->file('file'), $req->folder_id),201);
        });
    }

    #[OA\Get(
        path: "/api/storage/folder/content",
        tags: ["Folders"],
        summary: "Listar contenido de carpeta raíz",
        description: "Retorna el contenido de la carpeta raíz del usuario.",
        security: [["bearerAuth" => []]],
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
        ],
        responses: [
            new OA\Response(response: 200, description: "Contenido de la carpeta"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function getFolderContent(?string $folder_Id = null){
        return $this->handleStorageErrors(function() use ($folder_Id){
            $user = Security::isOwner();
            return response()->json($this->storageService->getFolderContent($user->id,$folder_Id));
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
            return response()->json($this->storageService->getFolder($user->id, $folder_Id));
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
            return response()->json($this->storageService->getFile($user->id,$file_id));
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
            return response()->json($this->storageService->delete($user->id,$folder_id,"folder"));
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
            return response()->json($this->storageService->delete($user->id,$file_id,"file"));
        });
    }

    #[OA\Post(
        path: "/api/storage/folder/{folder_id}/restore",
        tags: ["Folders"],
        summary: "Restaurar carpeta",
        description: "Restaura una carpeta desde la papelera.",
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
                $this->storageService->restoreFolder(
                    $this->storageService->getTrashedFolder($user->id,$folder_id)
                )
            );
        });
    }

    #[OA\Post(
        path: "/api/storage/file/{file_id}/restore",
        tags: ["Files"],
        summary: "Restaurar archivo",
        description: "Restaura un archivo desde la papelera.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Archivo restaurado"),
            new OA\Response(response: 400, description: "Archivo no está en papelera"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function restoreFile(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            return response()->json(
                $this->storageService->restoreFile(
                    $this->storageService->getTrashedFile($user->id,$file_id)
                )
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/file/{file_id}/move",
        tags: ["Files"],
        summary: "Mover archivo",
        description: "Mueve un archivo a otra carpeta.",
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

            $file = $this->storageService->getFile($user->id, $file_id);

            return response()->json(
                $this->storageService->moveFile(
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
        description: "Mueve una carpeta a otra ubicación.",
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

            $folder = $this->storageService->getFolder(
                $user->id,
                $folder_id
            );

            return response()->json(
                $this->storageService->moveFolder(
                    $folder,
                    $request->destination_folder_id
                )
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/file/{file_id}/rename",
        tags: ["Files"],
        summary: "Renombrar archivo",
        description: "Cambia el nombre de un archivo existente.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "nuevo_nombre.pdf"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Archivo renombrado"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function renameFile(string $file_id, RenameRequest $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
            $user = Security::isOwner();

            $file = $this->storageService->getFile($user->id, $file_id);

            return response()->json(
                $this->storageService->renameFile($file, $request->name)
            );
        });
    }

    #[OA\Patch(
        path: "/api/storage/folder/{folder_id}/rename",
        tags: ["Folders"],
        summary: "Renombrar carpeta",
        description: "Cambia el nombre de una carpeta existente.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "folder_id", in: "path", required: true, description: "ID de la carpeta (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Nuevo nombre"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Carpeta renombrada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Carpeta no encontrada"),
        ]
    )]
    public function renameFolder(string $folder_id, RenameRequest $request){
        return $this->handleStorageErrors(function() use ($folder_id, $request){
            $user = Security::isOwner();

            $folder = $this->storageService->getFolder($user->id, $folder_id);

            return response()->json(
                $this->storageService->renameFolder($folder, $request->name)
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
                $this->storageService->deleteTrash($user->id)
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
            return response()->json(
                $this->storageService->deletePermanent($user->id, $id, $type)
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
                $this->storageService->getTrash($user->id)
            );
        });
    }
}
