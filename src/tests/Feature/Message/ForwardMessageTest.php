<?php

namespace Tests\Feature\Message;

use App\Models\User;
use App\Services\ChatService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForwardMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_forward_message_between_chats_they_participate_in(): void
    {
        $user = User::factory()->create();
        $peerA = User::factory()->create();
        $peerB = User::factory()->create();

        $chat1 = app(ChatService::class)->createPrivateChat($user, $peerA);
        $chat2 = app(ChatService::class)->createPrivateChat($user, $peerB);

        $message = app(MessageService::class)->sendMessage($chat1, $user, 'original');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/forward", [
                'chat_id' => $chat2->id,
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'original')
            ->assertJsonPath('message.chat_id', $chat2->id);
    }
}
