<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public Chat $chat,
        public ?Message $lastMessage = null
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'chat' => [
                'id' => $this->chat->id,
                'type' => $this->chat->type,
                'users' => $this->chat->users->map(fn ($u) => [
                    'id' => $u->id,
                    'nickname' => $u->nickname,
                    'name' => $u->name,
                    'email' => $u->email,
                ]),
                'last_message' => $this->lastMessage ? [
                    'id' => $this->lastMessage->id,
                    'body' => $this->lastMessage->body,
                    'created_at' => $this->lastMessage->created_at,
                    'sender' => [
                        'id' => $this->lastMessage->sender->id,
                        'nickname' => $this->lastMessage->sender->nickname,
                        'name' => $this->lastMessage->sender->name,
                        'email' => $this->lastMessage->sender->email,
                    ],
                ] : null,
            ],
        ];
    }
}
