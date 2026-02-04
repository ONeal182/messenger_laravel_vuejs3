<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChatsController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    #[OA\Post(
        path: "/api/chats/group",
        summary: "Создать групповой чат",
        tags: ["Chats"],
        security: [["BearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "nicknames"],
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Команда проекта"),
                    new OA\Property(
                        property: "nicknames",
                        type: "array",
                        items: new OA\Items(type: "string"),
                        example: ["alice", "bob"]
                    ),
                ],
                example: [
                    "title" => "Команда проекта",
                    "nicknames" => ["alice", "bob"]
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Чат создан",
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: "chat", ref: "#/components/schemas/Chat"),
                ])
            ),
            new OA\Response(response: 401, description: "Не авторизован"),
            new OA\Response(response: 422, description: "Ошибки валидации"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'nicknames'  => ['required', 'array', 'min:1'],
            'nicknames.*'=> ['string', 'min:1'],
        ]);

        $memberIds = User::whereIn('nickname', $data['nicknames'])
            ->pluck('id')
            ->all();

        if (empty($memberIds)) {
            return response()->json([
                'message' => 'Указанные пользователи не найдены',
            ], 422);
        }

        $chat = $this->chatService->createGroup(
            $request->user(),
            $data['title'],
            $memberIds
        );

        return response()->json([
            'chat' => $chat->load('users:id,email,name,nickname'),
        ], 201);
    }
}
