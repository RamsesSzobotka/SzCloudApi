<?php

namespace App\Http\Controllers;

use App\Models\Expansion;
use App\Services\StorageUsageService;
use App\utils\HttpError;
use App\utils\Security;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use OpenApi\Attributes as OA;

class ExpansionController extends Controller
{
    public function __construct(
        private StorageUsageService $storageUsageService,
    ){}

    #[OA\Get(
        path: "/api/expansions",
        tags: ["Expansions"],
        summary: "Listar expansiones disponibles",
        description: "Retorna el catálogo de expansiones de almacenamiento disponibles para compra.",
        responses: [
            new OA\Response(response: 200, description: "Lista de expansiones"),
        ]
    )]
    public function index()
    {
        return response()->json(Expansion::all());
    }

    #[OA\Get(
        path: "/api/expansions/{id}",
        tags: ["Expansions"],
        summary: "Obtener info de una expansión",
        description: "Retorna los detalles de una expansión específica.",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID de la expansión", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalles de la expansión"),
            new OA\Response(response: 404, description: "Expansión no encontrada"),
        ]
    )]
    public function show(string $id)
    {
        try {
            return response()->json(Expansion::findOrFail($id));
        } catch (ModelNotFoundException $e) {
            abort(404, "Expansión no encontrada");
        }
    }

    #[OA\Post(
        path: "/api/expansions/{id}/buy",
        tags: ["Expansions"],
        summary: "Comprar una expansión",
        description: "Registra la compra de una expansión y suma el espacio al límite del usuario.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID de la expansión", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Expansión aplicada"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Expansión no encontrada"),
        ]
    )]
    public function buy(string $id)
    {
        try {
            $user = Security::isOwner();
            $expansion = Expansion::findOrFail($id);

            $success = $this->storageUsageService->applyExpansion($user, $expansion);

            if (!$success) {
                abort(400, "No se pudo aplicar la expansión");
            }

            return response()->json([
                "message" => "Expansión aplicada correctamente",
                "storage_limit" => $user->fresh()->storage_limit,
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, "Expansión no encontrada");
        } catch (HttpException $e) {
            throw $e;
        } catch (Exception $e) {
            HttpError::InternalError($e);
        }
    }
}
