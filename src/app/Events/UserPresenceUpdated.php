<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $chatId,
        public int $userId,
        public string $userName,
        public string $userEmail,
        public string $lastSeenAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->chatId);
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId,
            'user' => [
                'id'    => $this->userId,
                'name'  => $this->userName,
                'email' => $this->userEmail,
            ],
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}
