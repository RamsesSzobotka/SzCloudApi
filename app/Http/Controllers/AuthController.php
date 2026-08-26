<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Sesion;
use App\Services\UserService;
use App\utils\ExceptionCustom\DuplicateException;
use App\utils\HttpError;
use App\utils\Security;
use Exception;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;


class AuthController extends Controller
{
    public function __construct(
        private UserService $userService,
    )
    {}

    private function handleAuthErrors(callable $action)
    {
        try {
            return $action();
        } catch (DuplicateException $e) {
            abort(409, $e->getMessage());
        } catch (Exception $e) {
            HttpError::InternalError($e);
        }
    }

    // ─── Login ────────────────────────────────────────────────────

    #[OA\Post(
        path: "/api/login",
        tags: ["Auth"],
        summary: "Iniciar sesión",
        description: "Autentica al usuario y retorna tokens en cookies httponly.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Sesión iniciada — tokens en cookies",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Sesión iniciada correctamente"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Credenciales incorrectas"),
        ]
    )]
    public function login(LoginRequest $req)
    {
        return $this->handleAuthErrors(function () use ($req) {
            $credentials = $req->validated();

            if (!$token = auth("api")->attempt($credentials)) {
                return response()->json([
                    "message" => "Credenciales incorrectas"
                ], 401);
            }

            $refreshToken = Str::random(80);

            Sesion::create([
                'user_id' => auth("api")->user()->id,
                'refresh_token_hash' => hash('sha256', $refreshToken),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'expires_at' => now()->addDays(7),
            ]);

            return Security::withAuthCookies(
                response()->json(),
                $token,
                $refreshToken
            );
        });
    }

    // ─── Refresh ──────────────────────────────────────────────────

    #[OA\Post(
        path: "/api/refresh",
        tags: ["Auth"],
        summary: "Renovar access token",
        description: "Valida el refresh token y genera un nuevo access token. Rota el refresh token.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Access token renovado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Token renovado"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Refresh token inválido o expirado"),
        ]
    )]
    public function refreshToken()
    {
        return $this->handleAuthErrors(function () {
            $refreshToken = request()->cookie(Security::REFRESH_TOKEN);

            if (!$refreshToken) {
                return response()->json(['message' => 'Refresh token no proporcionado'], 401);
            }

            $session = Sesion::where('refresh_token_hash', hash('sha256', $refreshToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();

            if (!$session) {
                return Security::clearAuthCookies(
                    response()->json(['message' => 'Refresh token inválido'], 401)
                );
            }

            if ($session->hasExceededLifetime()) {
                $session->update(['revoked_at' => now()]);
                return Security::clearAuthCookies(
                    response()->json(['message' => 'Sesión expirada, inicie sesión nuevamente'], 401)
                );
            }

            // Rotación: revocar anterior, crear nueva
            $newRefreshToken = Str::random(80);

            $session->update(['revoked_at' => now()]);

            Sesion::create([
                'user_id' => $session->user_id,
                'refresh_token_hash' => hash('sha256', $newRefreshToken),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'expires_at' => now()->addDays(7),
            ]);

            $newAccessToken = auth("api")->tokenById($session->user_id);

            return Security::withAuthCookies(
                response()->json(['message' => 'Token renovado']),
                $newAccessToken,
                $newRefreshToken
            );
        });
    }

    // ─── Logout ───────────────────────────────────────────────────

    #[OA\Post(
        path: "/api/logout",
        tags: ["Auth"],
        summary: "Cerrar sesión",
        description: "Invalida el token JWT y revoca el refresh token.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Sesión cerrada"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function logout()
    {
        return $this->handleAuthErrors(function () {
            $user = auth("api")->user();

            if ($user) {
                Sesion::where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            auth("api")->logout();

            return Security::clearAuthCookies(
                response()->json(['message' => 'Sesión cerrada'])
            );
        });
    }

    // ─── Register ─────────────────────────────────────────────────

    #[OA\Post(
        path: "/api/register",
        tags: ["Auth"],
        summary: "Registrar usuario",
        description: "Crea una nueva cuenta de usuario.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "secret123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Usuario registrado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Usuario registrado correctamente."),
                        new OA\Property(property: "user", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 409, description: "El usuario ya se encuentra registrado"),
        ]
    )]
    public function register(RegisterRequest $req)
    {
        return $this->handleAuthErrors(function () use ($req) {
            $newUser = $this->userService->add($req->validated());

            return response()->json([
                'message' => 'Usuario registrado correctamente.',
                'user' => $newUser,
            ], 201);
        });
    }

    // ─── Me ───────────────────────────────────────────────────────

    #[OA\Get(
        path: "/api/me",
        tags: ["Auth"],
        summary: "Obtener usuario actual",
        description: "Retorna los datos del usuario autenticado.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Datos del usuario"),
            new OA\Response(response: 401, description: "No autenticado"),
            new OA\Response(response: 404, description: "Usuario no encontrado"),
        ]
    )]
    public function getMe()
    {
        return $this->handleAuthErrors(function () {
            return response()->json(auth('api')->user());
        });
    }
}
