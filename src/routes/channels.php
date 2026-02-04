<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Chat;

Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return Chat::where('id', $chatId)
        ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
