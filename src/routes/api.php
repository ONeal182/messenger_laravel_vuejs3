<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChatsController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/ping', [AuthController::class, 'ping']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Chats
    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/{chat}', [ChatController::class, 'show']);
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy']);
    Route::post('/chats/private', [ChatController::class, 'createPrivate']);
    Route::post('/chats/group', [ChatsController::class, 'store']);
    Route::post('/chats/{chat}/users', [ChatController::class, 'addUser']);
    Route::post('/chats/{chat}/read', [ChatController::class, 'markRead']);

    // Messages
    Route::get('/chats/{chat}/messages', [MessageController::class, 'index']);
    Route::get('/chats/{chat}/messages/search', [MessageController::class, 'search']);
    Route::post('/chats/{chat}/messages', [MessageController::class, 'store']);
    Route::delete('/messages/{message}', [MessageController::class, 'delete']); // удалить для себя
    Route::delete('/messages/{message}/all', [MessageController::class, 'destroy']); // удалить для всех
    Route::post('/messages/{message}/forward', [MessageController::class, 'forward']); // переслать
    Route::post('/chats/{chat}/typing', [MessageController::class, 'typing']);

    // Users
    Route::get('/users/search', [UserController::class, 'search']);
    Route::put('/profile', [UserController::class, 'update'])->middleware('profile.validate');
    Route::post('/profile/avatar', [UserController::class, 'uploadAvatar'])->middleware('avatar.validate');
});
