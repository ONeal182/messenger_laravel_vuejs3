<?php

namespace Tests\Feature\Message;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function member_can_send_message_to_chat(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $chat = app(ChatService::class)
            ->createPrivateChat($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'body' => 'Hello!',
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Hello!');
    }
}
