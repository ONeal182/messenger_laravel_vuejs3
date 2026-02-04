<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    #[OA\Get(
        path: "/api/users/search",
        summary: "Поиск пользователей по нику",
        tags: ["Users"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "nickname", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "oneal182", description: "Предпочтительный параметр для поиска по нику"),
            new OA\Parameter(name: "query", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "oneal", description: "Старое имя параметра, работает как алиас nickname"),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50), example: 10),
        ],
        responses: [
            new OA\Response(response: 200, description: "Результаты поиска"),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required_without:nickname', 'string', 'min:2'],
            'nickname' => ['required_without:query', 'string', 'min:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $search = $data['nickname'] ?? $data['query'];

        $users = $this->userService->searchByNickname(
            $search,
            $request->user()?->id, // исключаем текущего пользователя
            $data['limit'] ?? 10
        );

        return response()->json([
            'users' => $users,
        ]);
    }

    #[OA\Put(
        path: "/api/profile",
        summary: "Обновить профиль пользователя",
        tags: ["Users"],
        security: [["BearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                description: "Изменяемые поля профиля",
                properties: [
                    new OA\Property(property: "name", type: "string", nullable: true, example: "John"),
                    new OA\Property(property: "last_name", type: "string", nullable: true, example: "Doe"),
                    new OA\Property(property: "nickname", type: "string", example: "johnny"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "status", type: "string", nullable: true, example: "Working"),
                ],
                example: [
                    "name" => "John",
                    "last_name" => "Doe",
                    "nickname" => "johnny",
                    "email" => "john@example.com",
                    "status" => "Working"
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Профиль обновлен",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "user", ref: "#/components/schemas/User"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->all());

        return response()->json(['user' => $user]);
    }

    #[OA\Post(
        path: "/api/profile/avatar",
        summary: "Загрузить аватар",
        tags: ["Users"],
        security: [["BearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["avatar"],
                    properties: [
                        new OA\Property(property: "avatar", type: "string", format: "binary", description: "Файл изображения"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Аватар обновлен"),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $this->userService->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json(['user' => $user]);
    }
}
