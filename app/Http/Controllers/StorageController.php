<?php

namespace App\Http\Controllers;

use App\utils\HttpError;
use App\utils\LoggerHelper;
use App\utils\ExceptionCustom\StorageException;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use FolderDto;
use Illuminate\Http\Request;
use Security;
use StorageService;

class StorageController extends Controller
{
    public function __construct(
        private StorageService $storageService   
    ){}

    public function postFolder(Request $req){
        try{
            $user = Security::isOwner();
            return $this->storageService->addFolder(new FolderDto($user->id,$req->parent_id,$req->name));
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

    public function getFolderContent(?string $folder_Id = null){
        try{
            $user = Security::isOwner();
            return response()->json($this->storageService->getFolderContent($user->id,$folder_Id));
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

    public function getFolderInfo(?string $folder_Id = null){
        try{
            $user = Security::isOwner();
            return response()->json($this->storageService->getFolder($user->id, $folder_Id));
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

    public function getFile(string $file_id){
        try{
            $user = Security::isOwner();
            return response()->json($this->storageService->getFile($user->id,$file_id));
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

    public function deleteFolder(string $folder_id){
        try{
            $user = Security::isOwner();
            return response()->json($this->storageService->delete($user->id,$folder_id,"folder"));
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

    public function deleteFile(string $file_id){
        try{
            $user = Security::isOwner();
            return response()->json($this->storageService->delete($user->id,$file_id,"file"));
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

    public function restoreFolder(string $folder_id){
        try{
            $user = Security::isOwner();
            return response()->json(
                    $this->storageService->restoreFolder(
                        $this->storageService->getFolder($user->id,$folder_id)
                    )
                );
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
    
    public function restoreFile(string $folder_id){
        try{
            $user = Security::isOwner();
            return response()->json(
                $this->storageService->restoreFile(
                    $this->storageService->getFile($user->id,$folder_id)
                )
            );
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

    public function moveFile(string $file_id, Request $request){
        try{
            $user = Security::isOwner();

            $request->validate([
                "destination_folder_id" => ["nullable", "string"],
            ]);

            $file = $this->storageService->getFile($user->id, $file_id);

            return response()->json(
                $this->storageService->moveFile(
                    $file,
                    $request->destination_folder_id
                )
            );
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

    public function moveFolder(string $folder_id, Request $request){
        try{
            $user = Security::isOwner();

            $request->validate([
                "destination_folder_id" => ["nullable", "string"],
            ]);

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

    public function renameFile(string $file_id, Request $request){
        try{
            $user = Security::isOwner();

            $request->validate([
                "name" => ["required", "string", "max:255"],
            ]);

            $file = $this->storageService->getFile($user->id, $file_id);

            return response()->json(
                $this->storageService->renameFile($file, $request->name)
            );
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

    public function renameFolder(string $folder_id, Request $request){
        try{
            $user = Security::isOwner();

            $request->validate([
                "name" => ["required", "string", "max:255"],
            ]);

            $folder = $this->storageService->getFolder($user->id, $folder_id);

            return response()->json(
                $this->storageService->renameFolder($folder, $request->name)
            );
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
}
