<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShareLinkRequest;
use App\Services\FileService;
use App\Services\ShareLinkService;
use App\utils\LoggerHelper;
use App\utils\Security;
use App\utils\ExceptionCustom\ShareLinkException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;
use OpenApi\Attributes as OA;

class ShareLinkController extends Controller
{
    public function __construct(
        private ShareLinkService $shareLinkService,
        private FileService $fileService
    ){}

    private function handleShareLinkErrors(callable $action){
        try{
            return $action();
        }catch(ShareLinkException $e){
            abort(400, $e->getMessage());
        }catch(ModelNotFoundException $e){
            abort(404, $e->getMessage());
        }catch(ValidationException $e){
            abort(422, $e->getMessage());
        }catch(Exception $e){
            LoggerHelper::exception($e);
        }
    }

    #[OA\Post(
        path: "/api/share/file/{file_id}",
        tags: ["ShareLinks"],
        summary: "Crear enlace de compartir",
        description: "Genera un enlace de compartir para un archivo. Requiere autenticación.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "file_id", in: "path", required: true, description: "ID del archivo (UUID).", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "password", type: "string", format: "password", nullable: true, description: "Contraseña opcional para proteger el enlace"),
                    new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true, description: "Fecha de expiración del enlace"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Enlace creado"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Archivo no encontrado"),
        ]
    )]
    public function createShareLink(string $fileId, ShareLinkRequest $req){
        return $this->handleShareLinkErrors(function() use ($fileId, $req){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $fileId);

            return response()->json([
                "shareLink" => $this->shareLinkService->createShareLink($file, $req->validated())
            ]);
        });
    }

    #[OA\Get(
        path: "/api/share/{token}/data",
        tags: ["ShareLinks"],
        summary: "Obtener datos del enlace de compartir",
        description: "Retorna los datos completos de un enlace de compartir (URL firmada, permisos, etc). Requiere ser el propietario del archivo.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "token", in: "path", required: true, description: "Token del enlace de compartir.", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Datos del enlace"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Enlace no encontrado"),
        ]
    )]
    public function getShareLinkData(string $token){
        return $this->handleShareLinkErrors(function() use ($token){
            $user = Security::isOwner();
            $data = $this->shareLinkService->getShareLinkData($user->id, $token);

            return response()->json(["shareLink" => $data]);
        });
    }

    #[OA\Post(
        path: "/api/share/{token}",
        tags: ["ShareLinks"],
        summary: "Acceder a enlace de compartir",
        description: "Retorna la URL de descarga del archivo compartido. Si el enlace tiene contraseña, debe proporcionarse en el body.",
        parameters: [
            new OA\Parameter(name: "token", in: "path", required: true, description: "Token del enlace de compartir.", schema: new OA\Schema(type: "string")),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "password", type: "string", format: "password", description: "Contraseña del enlace (si aplica)"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "URL de descarga"),
            new OA\Response(response: 400, description: "Contraseña incorrecta"),
            new OA\Response(response: 404, description: "Enlace no encontrado o expirado"),
        ]
    )]
    public function getShareLink(string $token, Request $req){
        return $this->handleShareLinkErrors(function() use ($token, $req){
            $url = $this->shareLinkService->getShareLink($token, $req->input("password"));

            return response()->json(["url" => $url]);
        });
    }

    #[OA\Get(
        path: "/api/share/{token}/config",
        tags: ["ShareLinks"],
        summary: "Obtener configuración del enlace",
        description: "Retorna la configuración pública de un enlace de compartir (si requiere contraseña, si ha expirado, etc). Endpoint público.",
        parameters: [
            new OA\Parameter(name: "token", in: "path", required: true, description: "Token del enlace de compartir.", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Configuración del enlace"),
            new OA\Response(response: 404, description: "Enlace no encontrado"),
        ]
    )]
    public function getShareLinkConfig(string $token){
        return $this->handleShareLinkErrors(function() use ($token){
            return response()->json([
                "config" => $this->shareLinkService->getShareLinkConfig($token)
            ]);
        });
    }
}
