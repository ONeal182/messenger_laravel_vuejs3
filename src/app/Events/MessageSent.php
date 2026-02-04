<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('sender:id,email,name');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->message->chat_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'         => $this->message->id,
                'chat_id'    => $this->message->chat_id,
                'body'       => $this->message->body,
                'created_at' => $this->message->created_at,
                'sender'     => [
                    'id'    => $this->message->sender->id,
                    'email' => $this->message->sender->email,
                    'name'  => $this->message->sender->name,
                ],
            ],
        ];
    }
}
