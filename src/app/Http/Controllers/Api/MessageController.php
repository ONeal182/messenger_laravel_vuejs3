<?php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\Message;
use App\Events\UserTyping;
use Illuminate\Http\Request;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService
    ) {}

    /**
     * POST /api/chats/{chat}/messages
     */
    public function store(Request $request, Chat $chat): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
        ]);

        $message = $this->messageService->sendMessage(
            $chat,
            $request->user(),
            $request->body
        );

        return response()->json([
            'message' => $message->load('sender:id,email,name'),
        ], 201);
    }

    /**
     * GET /api/chats/{chat}/messages
     */
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

    /**
     * GET /api/chats/{chat}/messages/search
     */
    public function search(Request $request, Chat $chat): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

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

    /**
     * DELETE /api/messages/{message}
     * Удалить сообщение только для себя
     */
    public function delete(Request $request, Message $message): JsonResponse
    {
        $this->messageService->deleteForUser($message, $request->user());

        return response()->json(null, 204);
    }

    public function destroy(Request $request, Message $message){
        $this->messageService->deleteForAll($message, $request->user());

        return response()->json(null, 204);
    }

    /**
     * POST /api/messages/{message}/forward
     */
    public function forward(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => ['required', 'exists:chats,id'],
        ]);

        $newMessage = $this->messageService->forwardMessage(
            $message,
            $request->user(),
            Chat::findOrFail($data['chat_id'])
        );

        return response()->json(['message' => $newMessage], 201);
    }

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
