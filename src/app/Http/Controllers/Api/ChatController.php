<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Chat;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * POST /api/chats/private
     */
    public function createPrivate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $chat = $this->chatService->createPrivateChat(
            $request->user(),
            User::findOrFail($request->user_id)
        );

        return response()->json([
            'chat' => $chat->load('users:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path'),
        ]);
    }

    /**
     * GET /api/chats
     */
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

    /**
     * DELETE /api/chats/{chat}
     */
    public function destroy(Request $request, Chat $chat): JsonResponse
    {
        $this->chatService->deleteChat($chat, $request->user());

        return response()->json(null, 204);
    }

    /**
     * POST /api/chats/{chat}/users
     */
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

    /**
     * POST /api/chats/{chat}/read
     */
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
