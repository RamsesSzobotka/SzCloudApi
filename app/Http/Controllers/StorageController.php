<?php

namespace App\Http\Controllers;

use App\utils\HttpError;
use App\utils\LoggerHelper;
use App\utils\ExceptionCustom\StorageException;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use FolderDto;
use Security;
use Exception;
use StorageService;

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

    public function postFolder(Request $req){
        return $this->handleStorageErrors(function() use ($req){
            $req->validate([
                "name" => ["required", "string", "max:255"],
                "parent_id" => ["nullable", "string"],
            ]);

            $user = Security::isOwner();
            return $this->storageService->addFolder(new FolderDto($user->id, $req->name, $req->parent_id));
        });
    }

    public function postFile(Request $req){
        return $this->handleStorageErrors(function() use ($req){
            $req->validate([
                "file" => ["required", "file", "max:102400"],
                "folder_id" => ["required", "string"],
            ]);

            $user = Security::isOwner();
            return response()->json(
                $this->storageService->addFile($user->id, $req->file('file'), $req->folder_id),
                201
            );
        });
    }

    public function getFolderContent(?string $folder_Id = null){
        return $this->handleStorageErrors(function() use ($folder_Id){
            $user = Security::isOwner();
            return response()->json($this->storageService->getFolderContent($user->id,$folder_Id));
        });
    }

    public function getFolderInfo(?string $folder_Id = null){
        return $this->handleStorageErrors(function() use ($folder_Id){
            $user = Security::isOwner();
            return response()->json($this->storageService->getFolder($user->id, $folder_Id));
        });
    }

    public function getFile(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            return response()->json($this->storageService->getFile($user->id,$file_id));
        });
    }

    public function deleteFolder(string $folder_id){
        return $this->handleStorageErrors(function() use ($folder_id){
            $user = Security::isOwner();
            return response()->json($this->storageService->delete($user->id,$folder_id,"folder"));
        });
    }

    public function deleteFile(string $file_id){
        return $this->handleStorageErrors(function() use ($file_id){
            $user = Security::isOwner();
            return response()->json($this->storageService->delete($user->id,$file_id,"file"));
        });
    }

    public function restoreFolder(string $folder_id){
        return $this->handleStorageErrors(function() use ($folder_id){
            $user = Security::isOwner();
            return response()->json(
                $this->storageService->restoreFolder(
                    $this->storageService->getFolder($user->id,$folder_id)
                )
            );
        });
    }

    public function restoreFile(string $folder_id){
        return $this->handleStorageErrors(function() use ($folder_id){
            $user = Security::isOwner();
            return response()->json(
                $this->storageService->restoreFile(
                    $this->storageService->getFile($user->id,$folder_id)
                )
            );
        });
    }

    public function moveFile(string $file_id, Request $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
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
        });
    }

    public function moveFolder(string $folder_id, Request $request){
        return $this->handleStorageErrors(function() use ($folder_id, $request){
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
        });
    }

    public function renameFile(string $file_id, Request $request){
        return $this->handleStorageErrors(function() use ($file_id, $request){
            $user = Security::isOwner();

            $request->validate([
                "name" => ["required", "string", "max:255"],
            ]);

            $file = $this->storageService->getFile($user->id, $file_id);

            return response()->json(
                $this->storageService->renameFile($file, $request->name)
            );
        });
    }

    public function renameFolder(string $folder_id, Request $request){
        return $this->handleStorageErrors(function() use ($folder_id, $request){
            $user = Security::isOwner();

            $request->validate([
                "name" => ["required", "string", "max:255"],
            ]);

            $folder = $this->storageService->getFolder($user->id, $folder_id);

            return response()->json(
                $this->storageService->renameFolder($folder, $request->name)
            );
        });
    }
}
