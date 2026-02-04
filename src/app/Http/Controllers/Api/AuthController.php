<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    #[OA\Post(
        path: "/api/auth/register",
        summary: "Регистрация нового пользователя",
        description: "Возвращает bearer-токен для дальнейших запросов. После регистрации сразу можно использовать его в заголовке `Authorization: Bearer <token>`.",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nickname", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "nickname", type: "string", example: "john_doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "secret123"),
                ],
                example: [
                    "nickname" => "john_doe",
                    "email" => "user@example.com",
                    "password" => "secret123",
                    "password_confirmation" => "secret123"
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Успешная регистрация",
                content: new OA\JsonContent(ref: "#/components/schemas/AuthTokenResponse")
            ),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json(
            $this->authService->register(
                $request->nickname,
                $request->email,
                $request->password
            )
        );
    }

    #[OA\Post(
        path: "/api/auth/login",
        summary: "Аутентификация и получение токена",
        description: "Возвращает bearer-токен Sanctum. Используй его в `Authorization: Bearer <token>` для всех защищённых эндпоинтов.",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nickname", "password"],
                properties: [
                    new OA\Property(property: "nickname", type: "string", example: "john_doe"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret123"),
                ],
                example: [
                    "nickname" => "john_doe",
                    "password" => "secret123"
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Успешный вход",
                content: new OA\JsonContent(ref: "#/components/schemas/AuthTokenResponse")
            ),
            new OA\Response(response: 401, description: "Неверные учетные данные"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json(
            $this->authService->login(
                $request->nickname,
                $request->password
            )
        );
    }

    #[OA\Get(
        path: "/api/auth/me",
        summary: "Текущий пользователь",
        description: "Передай bearer-токен в заголовке `Authorization: Bearer <token>`.",
        tags: ["Auth"],
        security: [["BearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Текущий пользователь",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Выход (отзыв текущего токена)",
        description: "Отзывает только тот токен, который передан в заголовке `Authorization: Bearer <token>`.",
        tags: ["Auth"],
        security: [["BearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Выход выполнен, токен отозван"),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    #[OA\Post(
        path: "/api/auth/ping",
        summary: "Обновить online-статус",
        description: "Периодический пинг для присутствия. Требуется bearer-токен. Ограничение: до 30 запросов в минуту (throttle:30,1).",
        tags: ["Auth"],
        security: [["BearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "OK"),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 429, description: "Слишком часто — подожди"),
        ]
    )]
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        // Онлайн-статус в Redis (TTL ~70 сек); если Redis недоступен — тихо игнорируем.
        try {
            Cache::put($this->onlineCacheKey($user->id), now()->toISOString(), 70);
        } catch (Throwable $e) {
            // no-op
        }

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $chatIds = $user->chats()->pluck('chats.id');

        foreach ($chatIds as $chatId) {
            broadcast(new \App\Events\UserPresenceUpdated(
                $chatId,
                $user->id,
                $user->name ?? $user->email,
                $user->email,
                $user->last_seen_at?->toISOString() ?? now()->toISOString()
            ))->toOthers();
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }

    private function onlineCacheKey(int $userId): string
    {
        return "online_user:{$userId}";
    }
}
