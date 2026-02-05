<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Events\MessageSent;
use App\Events\ChatUpdated;
use App\Events\MessageRead;
use App\Events\MessageDeleted;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class MessageService
{

    public function sendMessage(Chat $chat, User $user, string $body): Message
    {
        $this->assertMember($chat, $user);
        $this->forgetRecentCache($chat->id);
        $this->forgetUserChatsCache($chat);

        // 1️⃣ Создаём сообщение
        $message = Message::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'body'    => $body,
        ]);

        // 2️⃣ Отправляем событие в WebSocket
        logger('🔥 BEFORE BROADCAST');

        broadcast(new MessageSent($message))->toOthers();

        // Уведомим участников о новом чате/сообщении в их личных каналах,
        // чтобы чат появился без перезагрузки
        $chat->loadMissing([
            'users:id,nickname,name,email,avatar_path,avatar_thumb_path',
        ]);
        $message->loadMissing('sender:id,nickname,name,email,last_name,avatar_path,avatar_thumb_path');

        foreach ($chat->users as $participant) {
            if ($participant->id === $user->id) {
                continue;
            }
            broadcast(new ChatUpdated(
                $participant->id,
                $chat,
                $message
            ))->toOthers();
        }

        logger('🔥 AFTER BROADCAST');

        return $message;
    }
      

    public function getChatMessages(Chat $chat, User $user, int $perPage = 20): LengthAwarePaginator
    {
        $this->assertMember($chat, $user);

        // include pivot for read tracking
        // Получаем свежие pivot-данные по другим участникам (без зависимости от уже загруженной relation)
        $othersPivot = $chat->users()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'chat_user.last_read_message_id', 'chat_user.last_seen_at', 'chat_user.updated_at']);

        $messages = $chat->messages()
            ->with([
                'sender:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path',
                'forwardFromUser:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path',
            ])
            ->whereNull('deleted_for_all_at')
            ->whereNotExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('message_user_deletions')
                    ->whereColumn('message_user_deletions.message_id', 'messages.id')
                    ->where('message_user_deletions.user_id', $user->id);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $messages->getCollection()->transform(function ($message) use ($othersPivot, $user) {
            // Флаг read актуален только для исходящих сообщений текущего пользователя
            if ($message->user_id !== $user->id) {
                $message->setAttribute('read', false);
                return $message;
            }

            $readByOthers = $othersPivot->isNotEmpty()
                && $othersPivot->every(function ($participant) use ($message) {
                    $pivot = $participant->pivot;
                    if (! $pivot?->last_read_message_id) {
                        return false;
                    }
                    if ($pivot->last_read_message_id < $message->id) {
                        return false;
                    }
                    $lastSeen = $pivot->last_seen_at ? \Illuminate\Support\Carbon::parse($pivot->last_seen_at) : null;
                    if (! $lastSeen || $lastSeen->lt($message->created_at)) {
                        return false;
                    }
                    $pivotUpdated = $pivot->updated_at ? \Illuminate\Support\Carbon::parse($pivot->updated_at) : null;
                    if (! $pivotUpdated || $pivotUpdated->lt($message->created_at)) {
                        return false;
                    }
                    return true;
                });

            $message->setAttribute('read', $readByOthers);

            return $message;
        });

        return $messages;
    }

    public function searchMessages(Chat $chat, User $user, string $term, int $limit = 20): Collection
    {
        $this->assertMember($chat, $user);

        $othersPivot = $chat->users()
            ->where('users.id', '!=', $user->id)
            ->get(['users.id', 'chat_user.last_read_message_id', 'chat_user.last_seen_at', 'chat_user.updated_at']);

        $messages = $chat->messages()
            ->with([
                'sender:id,email,name,nickname,last_name',
                'forwardFromUser:id,email,name,nickname,last_name',
            ])
            ->whereNull('deleted_for_all_at')
            ->whereNotExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('message_user_deletions')
                    ->whereColumn('message_user_deletions.message_id', 'messages.id')
                    ->where('message_user_deletions.user_id', $user->id);
            })
            ->where('body', 'like', '%' . $term . '%')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $messages->transform(function ($message) use ($othersPivot, $user) {
            if ($message->user_id !== $user->id) {
                $message->setAttribute('read', false);
                return $message;
            }

            $readByOthers = $othersPivot->isNotEmpty()
                && $othersPivot->every(function ($participant) use ($message) {
                    $pivot = $participant->pivot;
                    if (! $pivot?->last_read_message_id) {
                        return false;
                    }
                    if ($pivot->last_read_message_id < $message->id) {
                        return false;
                    }
                    $lastSeen = $pivot->last_seen_at ? \Illuminate\Support\Carbon::parse($pivot->last_seen_at) : null;
                    if (! $lastSeen || $lastSeen->lt($message->created_at)) {
                        return false;
                    }
                    $pivotUpdated = $pivot->updated_at ? \Illuminate\Support\Carbon::parse($pivot->updated_at) : null;
                    if (! $pivotUpdated || $pivotUpdated->lt($message->created_at)) {
                        return false;
                    }
                    return true;
                });

            $message->setAttribute('read', $readByOthers);

            return $message;
        });

        return $messages;
    }

    /**
     * Скрыть сообщение только для текущего пользователя
     */
    public function deleteForUser(Message $message, User $user): void
    {
        $chat = $message->chat()->firstOrFail();
        $this->assertMember($chat, $user);
        $this->forgetRecentCache($chat->id);
        $this->forgetUserChatsCache($chat);

        DB::table('message_user_deletions')->updateOrInsert(
            [
                'message_id' => $message->id,
                'user_id'    => $user->id,
            ],
            [
                'deleted_at' => now(),
            ]
        );
    }

    /**
     * Пометить сообщение удаленным для всех участников (только автор)
     */
    public function deleteForAll(Message $message, User $user): void
    {
        $chat = $message->chat()->firstOrFail();
        $this->assertMember($chat, $user);
        $this->forgetRecentCache($chat->id);
        $this->forgetUserChatsCache($chat);

        if ($message->user_id !== $user->id) {
            throw new AuthorizationException('Only the author can delete this message for all');
        }

        if ($message->deleted_for_all_at) {
            return;
        }

        $message->forceFill(['deleted_for_all_at' => now()])->save();

        broadcast(new MessageDeleted($message->chat_id, $message->id, 'all'))->toOthers();
    }

    /**
     * Переслать сообщение в другой чат
     */
    public function forwardMessage(Message $message, User $user, Chat $targetChat): Message
    {
        // проверяем членство в исходном и целевом чатах
        $sourceChat = $message->chat()->firstOrFail();
        $this->assertMember($sourceChat, $user);
        $this->assertMember($targetChat, $user);
        $this->forgetRecentCache($targetChat->id);
        $this->forgetUserChatsCache($targetChat);

        if ($message->deleted_for_all_at) {
            throw new AuthorizationException('Message is deleted');
        }

        $forward = Message::create([
            'chat_id'                => $targetChat->id,
            'user_id'                => $user->id,
            'body'                   => $message->body,
            'forward_from_message_id'=> $message->id,
            'forward_from_user_id'   => $message->user_id,
            'forward_from_chat_id'   => $sourceChat->id,
        ]);

        $forward->load([
            'sender:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path',
            'forwardFromUser:id,email,name,nickname,last_name,avatar_path,avatar_thumb_path',
        ]);

        broadcast(new MessageSent($forward))->toOthers();

        return $forward;
    }

    private function assertMember(Chat $chat, User $user): void
    {
        $isMember = $chat->users()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('You are not a member of this chat');
        }
    }

    private function forgetRecentCache(int $chatId): void
    {
        foreach ([10, 20, 50] as $perPage) {
            Cache::forget("chat:{$chatId}:recent:per{$perPage}");
        }
    }

    private function forgetUserChatsCache(Chat $chat): void
    {
        $chat->loadMissing('users:id');
        foreach ($chat->users as $participant) {
            Cache::forget("user:{$participant->id}:chats_with_unread");
        }
    }
}
