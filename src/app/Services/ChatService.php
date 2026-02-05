<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use App\Events\UserPresenceUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ChatService
{
    public function createPrivateChat(User $userA, User $userB): Chat
    {
        return DB::transaction(function () use ($userA, $userB) {
    
            $existingChat = Chat::where('type', 'private')
                ->whereHas('users', fn ($q) => $q->where('users.id', $userA->id))
                ->whereHas('users', fn ($q) => $q->where('users.id', $userB->id))
                ->first();
    
            if ($existingChat) {
                return $existingChat;
            }
    
            $chat = Chat::create([
                'type' => 'private',
            ]);
    
            $chat->users()->attach([
                $userA->id => ['role' => 'member'],
                $userB->id => ['role' => 'member'],
            ]);
    
            return $chat;
        });
    }

    public function createGroup(User $owner, string $title, array $memberIds = []): Chat
    {
        return DB::transaction(function () use ($owner, $title, $memberIds) {
            $chat = Chat::create([
                'type'  => 'group',
                'title' => $title,
            ]);

            $uniqueIds = collect($memberIds)
                ->filter(fn ($id) => $id && $id !== $owner->id)
                ->unique()
                ->values()
                ->all();

            $attach = [
                $owner->id => ['role' => 'owner', 'joined_at' => now()],
            ];

            foreach ($uniqueIds as $id) {
                $attach[$id] = ['role' => 'member', 'joined_at' => now()];
            }

            $chat->users()->attach($attach);

            return $chat;
        });
    }

    public function deleteChat(Chat $chat, User $user): void
    {
        $this->assertMember($chat, $user);
        $chat->delete();
    }

    public function getChatForUser(int $chatId, User $user): Chat
    {
        $chat = Chat::with(['users' => function ($q) {
            $q->select('users.id', 'users.email', 'users.name', 'users.nickname', 'users.last_seen_at', 'users.avatar_path', 'users.avatar_thumb_path');
        }])
            ->findOrFail($chatId);

        $isMember = $chat->users
            ->contains(fn ($u) => $u->id === $user->id);

        if (! $isMember) {
            throw new AuthorizationException('You are not a member of this chat');
        }

        $this->applyPresence($chat->users);
        $chat->typing_users = $this->getTypingUsers($chat, $user);

        return $chat;
    }

    public function getUserChats(User $user)
    {
        $chats = $user->chats()
            ->withPivot('role', 'joined_at', 'last_read_message_id', 'last_seen_at')
            ->with([
                'users:id,email,name,last_name,nickname,last_seen_at,avatar_path,avatar_thumb_path',
                'lastMessage.sender:id,email,name,last_name,nickname,avatar_path,avatar_thumb_path',
            ])
            ->get();

        // Обновляем last_message, исключая удаленные для всех и удаленные этим пользователем
        $chats->each(function (Chat $chat) use ($user) {
            $last = $chat->messages()
                ->whereNull('deleted_for_all_at')
                ->whereNotExists(function ($q) use ($user) {
                    $q->select(DB::raw(1))
                        ->from('message_user_deletions')
                        ->whereColumn('message_user_deletions.message_id', 'messages.id')
                        ->where('message_user_deletions.user_id', $user->id);
                })
                ->latest('id')
                ->with('sender:id,email,name,nickname,avatar_path,avatar_thumb_path')
                ->first();

            $chat->setRelation('lastMessage', $last);
            $this->applyPresence($chat->users);
        });

        return $chats;
    }

    public function getUserChatsWithUnread(User $user)
    {
        $chats = $user->chats()
            ->withPivot('role', 'joined_at', 'last_read_message_id', 'last_seen_at')
            ->with([
                'users:id,email,name,last_name,nickname,last_seen_at,avatar_path,avatar_thumb_path',
                'lastMessage.sender:id,email,name,last_name,nickname,avatar_path,avatar_thumb_path',
            ])
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->where(function ($query) {
                        $query
                            ->whereNull('chat_user.last_read_message_id')
                            ->orWhereColumn(
                                'messages.id',
                                '>',
                                'chat_user.last_read_message_id'
                            );
                    })->where('messages.user_id', '!=', $user->id);
                },
            ])
            ->get();

        $chats->each(function (Chat $chat) use ($user) {
            $last = $chat->messages()
                ->whereNull('deleted_for_all_at')
                ->whereNotExists(function ($q) use ($user) {
                    $q->select(DB::raw(1))
                        ->from('message_user_deletions')
                        ->whereColumn('message_user_deletions.message_id', 'messages.id')
                        ->where('message_user_deletions.user_id', $user->id);
                })
                ->latest('id')
                ->with('sender:id,email,name,nickname')
                ->first();

            $chat->setRelation('lastMessage', $last);
            $this->applyPresence($chat->users);
        });

        return $chats;
    }

    /**
     * Добавить пользователя по nickname в существующий чат (если actor — участник)
     */
    public function addUserByNickname(Chat $chat, User $actor, string $nickname): Chat
    {
        // убедиться, что инициатор состоит в чате
        $isMember = $chat->users()->where('users.id', $actor->id)->exists();
        if (! $isMember) {
            throw new AuthorizationException('You are not a member of this chat');
        }

        $userToAdd = User::where('nickname', $nickname)->firstOrFail();

        // уже внутри — возвращаем чат
        if ($chat->users()->where('users.id', $userToAdd->id)->exists()) {
            return $chat->load('users:id,email');
        }

        $chat->users()->attach($userToAdd->id, [
            'role'      => 'member',
            'joined_at' => now(),
        ]);

        return $chat->load('users:id,email');
    }

    /**
     * Mark messages as read up to given id (or latest) for user
     */
    public function markRead(Chat $chat, User $user, ?int $messageId = null): void
    {
        // ensure member
        $this->assertMember($chat, $user);

        $targetId = null;

        if ($messageId) {
            // Берём конкретное сообщение, убеждаемся что оно из этого чата и не моё
            $message = $chat->messages()
                ->where('id', $messageId)
                ->where('user_id', '!=', $user->id)
                ->first();

            $targetId = $message?->id;
        } else {
            // Если не указано — берём последний входящий (не мой) месседж
            $targetId = $chat->messages()
                ->where('user_id', '!=', $user->id)
                ->max('id');
        }

        if (! $targetId) {
            return;
        }

        $chat->users()->updateExistingPivot($user->id, [
            'last_read_message_id' => $targetId,
            'last_seen_at'         => now(),
        ]);

        // broadcast presence update for this chat
        broadcast(new UserPresenceUpdated(
            $chat->id,
            $user->id,
            $user->name ?? $user->email,
            $user->email,
            $user->last_seen_at?->toISOString() ?? now()->toISOString()
        ))->toOthers();

        broadcast(new \App\Events\MessageRead(
            $chat->id,
            $user->id,
            $user->name ?? $user->email,
            $targetId
        ))->toOthers();

        Cache::forget("user:{$user->id}:chats_with_unread");
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

    /**
     * Подмешивает признак online и обновляет last_seen_at из Redis, если есть свежий ключ.
     */
    private function applyPresence(Collection $users): void
    {
        try {
            if ($users->isEmpty()) {
                return;
            }

            $keys = [];
            foreach ($users as $user) {
                $keys[$user->id] = $this->presenceKey($user->id);
            }

            $cached = Cache::many(array_values($keys));
            $users->each(function ($user) use ($keys, $cached) {
                $cacheKey = $keys[$user->id] ?? null;
                if (! $cacheKey) {
                    return;
                }

                $value = $cached[$cacheKey] ?? null;
                $user->online = $value !== null;
                if ($value !== null) {
                    $user->last_seen_at = $value;
                }

                // typing по этому пользователю (для групп при необходимости можно расширить)
                // в этом методе не используем, но оставляем как задел: можно быстро проверить наличие ключа
            });
        } catch (Throwable $e) {
            // Redis недоступен — оставляем данные как есть (fallback на last_seen_at из БД)
        }
    }

    private function presenceKey(int $userId): string
    {
        return "online_user:{$userId}";
    }

    private function typingSetKey(int $chatId): string
    {
        return "typing:chat:{$chatId}:users";
    }

    private function getTypingUsers(Chat $chat, User $current): array
    {
        try {
            $userIds = Redis::smembers($this->typingSetKey($chat->id));
            if (! $userIds) {
                return [];
            }

            $typing = [];
            foreach ($userIds as $userId) {
                $userId = (int) $userId;
                if (! $userId || $userId === $current->id) {
                    continue;
                }

                $user = $chat->users->firstWhere('id', $userId) ?? User::find($userId);
                if (! $user) {
                    continue;
                }

                $typing[] = [
                    'id'    => $userId,
                    'label' => $this->displayName($user),
                ];
            }

            return $typing;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function displayName(User $user): string
    {
        $full = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($user->name && $user->last_name) {
            return $full;
        }
        if ($user->name) {
            return $user->name;
        }
        if ($user->nickname) {
            return $user->nickname;
        }
        return $user->email ?? '';
    }
}
