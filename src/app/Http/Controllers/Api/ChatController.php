<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Chat;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    #[OA\Post(
        path: "/api/chats/private",
        summary: "Создать личный чат",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["user_id"],
                properties: [
                    new OA\Property(property: "user_id", type: "integer", example: 12, description: "ID другого пользователя (не текущего)"),
                ],
                example: ["user_id" => 12]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Чат создан",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "chat", ref: "#/components/schemas/Chat"),
                ])
            ),
            new OA\Response(
                response: 409,
                description: "Личный чат уже существует",
                content: new OA\JsonContent(example: ["message" => "Private chat already exists", "chat_id" => 5])
            ),
            new OA\Response(response: 422, description: "Ошибки валидации"),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function createPrivate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id', 'different:user.id'],
        ]);

        $chat = $this->chatService->createPrivateChat(
            $request->user(),
            User::findOrFail($request->user_id)
        );

        // Если чат уже существовал — вернём 409 и id существующего чата
        if ($chat->wasRecentlyCreated === false && $chat->type === 'private') {
            return response()->json([
                'message' => 'Private chat already exists',
                'chat_id' => $chat->id,
            ], 409);
        }

        return response()->json([
            'chat' => $chat->load('users:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path'),
        ], $chat->wasRecentlyCreated ? 200 : 409);
    }

    #[OA\Get(
        path: "/api/chats",
        summary: "Список чатов пользователя с количеством непрочитанных",
        description: "Возвращает только чаты текущего авторизованного пользователя (bearer токен обязателен).",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Список чатов",
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: "chats",
                        type: "array",
                        items: new OA\Items(ref: "#/components/schemas/Chat")
                    ),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "user:{$user->id}:chats_with_unread";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json([
                'chats' => $cached,
            ]);
        }

        $chats = $this->chatService->getUserChatsWithUnread($user);
        Cache::put($cacheKey, $chats->toArray(), now()->addSeconds(60));
    
        return response()->json([
            'chats' => $chats,
        ]);
    }

    #[OA\Get(
        path: "/api/chats/{chat}",
        summary: "Получить информацию о чате",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Информация о чате",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "chat", ref: "#/components/schemas/Chat"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 404, description: "Чат не найден"),
        ]
    )]
    public function show(Request $request, int $chat): JsonResponse
    {
        $chat = $this->chatService->getChatForUser(
            $chat,
            $request->user()
        );

        return response()->json([
            'chat' => $chat,
        ]);
    }

    #[OA\Delete(
        path: "/api/chats/{chat}",
        summary: "Удалить чат",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10)
        ],
        responses: [
            new OA\Response(response: 204, description: "Удалено"),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 403, description: "Нет доступа"),
        ]
    )]
    public function destroy(Request $request, Chat $chat): JsonResponse
    {
        $this->chatService->deleteChat($chat, $request->user());

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/chats/{chat}/users",
        summary: "Добавить пользователя в чат по nickname",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nickname"],
                properties: [
                    new OA\Property(property: "nickname", type: "string", example: "nick"),
                ],
                example: ["nickname" => "nick"]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Пользователь добавлен",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "chat", ref: "#/components/schemas/Chat"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function addUser(Request $request, Chat $chat): JsonResponse
    {
        $request->validate([
            'nickname' => ['required', 'string', 'exists:users,nickname'],
        ]);

        $chat = $this->chatService->addUserByNickname(
            $chat,
            $request->user(),
            $request->nickname
        );

        return response()->json([
            'chat' => $chat,
        ]);
    }

    #[OA\Post(
        path: "/api/chats/{chat}/read",
        summary: "Отметить сообщения прочитанными",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message_id", type: "integer", nullable: true, example: 123),
                ],
                example: ["message_id" => 123]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: "Отмечено"),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function markRead(Request $request, Chat $chat): JsonResponse
    {
        $request->validate([
            'message_id' => ['nullable', 'integer'],
        ]);

        $this->chatService->markRead(
            $chat,
            $request->user(),
            $request->message_id
        );

        return response()->json(null, 204);
    }

}
