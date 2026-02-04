<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatsController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * POST /api/chats/group
     */
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
