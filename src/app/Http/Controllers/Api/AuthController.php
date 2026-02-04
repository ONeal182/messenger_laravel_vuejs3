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

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * POST /api/auth/register
     */
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

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json(
            $this->authService->login(
                $request->nickname,
                $request->password
            )
        );
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    /**
     * POST /api/auth/ping
     */
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
