<?php

namespace Tests\Feature\Message;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListMessagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function member_can_get_chat_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $chat = app(ChatService::class)
            ->createPrivateChat($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'body' => 'Hello!',
            ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/messages")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'body',
                        'sender' => ['id', 'email'],
                        'created_at',
                    ],
                ],
            ]);
    }
}
