<?php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\Message;
use App\Events\UserTyping;
use App\Services\MessageService;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\SearchMessagesRequest;
use App\Http\Requests\ForwardMessageRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;
use OpenApi\Attributes as OA;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService
    ) {}

    #[OA\Post(
        path: "/api/chats/{chat}/messages",
        summary: "Отправить сообщение в чат",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["body"],
                properties: [
                    new OA\Property(property: "body", type: "string", example: "Привет!"),
                ],
                example: ["body" => "Привет!"]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Сообщение отправлено",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "message", ref: "#/components/schemas/Message"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function store(SendMessageRequest $request, Chat $chat): JsonResponse
    {
        $message = $this->messageService->sendMessage(
            $chat,
            $request->user(),
            $request->body
        );

        return response()->json([
            'message' => $message->load('sender:id,email,name'),
        ], 201);
    }

    #[OA\Get(
        path: "/api/chats/{chat}/messages",
        summary: "Список сообщений чата",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", minimum: 1, maximum: 100), description: "Количество на странице (по умолчанию 10)", example: 10),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Список сообщений",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Message")
                        ),
                        new OA\Property(property: "links", nullable: true),
                        new OA\Property(property: "meta", nullable: true),
                    ],
                    description: "Пагинация Laravel (data/links/meta)"
                )
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function index(Request $request, Chat $chat): JsonResponse
    {
        // По умолчанию грузим по 10 сообщений, можно задавать per_page в запросе
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $messages = $this->messageService->getChatMessages(
            $chat,
            $request->user(),
            $perPage
        );

        return response()->json($messages);
    }

    #[OA\Get(
        path: "/api/chats/{chat}/messages/search",
        summary: "Поиск сообщений в чате",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10),
            new OA\Parameter(name: "query", in: "query", required: true, schema: new OA\Schema(type: "string"), example: "Привет"),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50), example: 20),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Результаты поиска",
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: "messages",
                        type: "array",
                        items: new OA\Items(ref: "#/components/schemas/Message")
                    ),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function search(SearchMessagesRequest $request, Chat $chat): JsonResponse
    {
        $data = $request->validated();

        $messages = $this->messageService->searchMessages(
            $chat,
            $request->user(),
            $data['query'],
            $data['limit'] ?? 20
        );

        return response()->json([
            'messages' => $messages,
        ]);
    }

    #[OA\Delete(
        path: "/api/messages/{message}",
        summary: "Удалить сообщение только для себя",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "message", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 501),
        ],
        responses: [
            new OA\Response(response: 204, description: "Удалено"),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function delete(Request $request, Message $message): JsonResponse
    {
        $this->messageService->deleteForUser($message, $request->user());

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: "/api/messages/{message}/all",
        summary: "Удалить сообщение для всех",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "message", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 501),
        ],
        responses: [
            new OA\Response(response: 204, description: "Удалено"),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 403, description: "Нет прав"),
        ]
    )]
    public function destroy(Request $request, Message $message){
        $this->messageService->deleteForAll($message, $request->user());

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/messages/{message}/forward",
        summary: "Переслать сообщение в другой чат",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "message", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 501),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["chat_id"],
                properties: [
                    new OA\Property(property: "chat_id", type: "integer", example: 55),
                ],
                example: ["chat_id" => 55]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Сообщение переслано",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "message", ref: "#/components/schemas/Message"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function forward(ForwardMessageRequest $request, Message $message): JsonResponse
    {
        $data = $request->validated();

        $newMessage = $this->messageService->forwardMessage(
            $message,
            $request->user(),
            Chat::findOrFail($data['chat_id'])
        );

        return response()->json(['message' => $newMessage], 201);
    }

    #[OA\Post(
        path: "/api/chats/{chat}/typing",
        summary: "Отметить набор текста пользователем",
        tags: ["Messages"],
        security: [["BearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "chat", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 10),
        ],
        responses: [
            new OA\Response(response: 204, description: "Статус обновлен"),
            new OA\Response(response: 401, description: "Не авторизован"),
        ]
    )]
    public function typing(Chat $chat, Request $request)
    {
        $user = $request->user();
        try {
            Cache::put($this->typingCacheKey($chat->id, $user->id), now()->toISOString(), 5);
            Redis::sadd($this->typingSetKey($chat->id), $user->id);
            Redis::expire($this->typingSetKey($chat->id), 5);
        } catch (Throwable $e) {
            // ignore redis errors
        }

        broadcast(new UserTyping($chat->id, $user))->toOthers();

        return response()->noContent();
    }

    private function typingCacheKey(int $chatId, int $userId): string
    {
        return "typing:chat:{$chatId}:user:{$userId}";
    }

    private function typingSetKey(int $chatId): string
    {
        return "typing:chat:{$chatId}:users";
    }
}
