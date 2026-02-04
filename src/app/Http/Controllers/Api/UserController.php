<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * GET /api/users/search?query=@nick
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $users = $this->userService->searchByNickname(
            $data['query'],
            $request->user()?->id,
            $data['limit'] ?? 10
        );

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * PUT /api/profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->all());

        return response()->json(['user' => $user]);
    }

    /**
     * POST /api/profile/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $this->userService->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json(['user' => $user]);
    }
}
