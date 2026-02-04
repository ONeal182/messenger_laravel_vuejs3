<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListChatsWithMetaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chat_list_contains_last_message_and_unread_count(): void
    {
        // Arrange — готовим данные
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $chat = app(ChatService::class)
            ->createPrivateChat($user, $other);

        // other отправляет сообщение
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'body' => 'Hello!',
            ])
            ->assertCreated();

        // Act — user получает список чатов
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/chats')
            ->assertOk();

        // Assert — проверяем мета-информацию
        $response
            ->assertJsonPath('chats.0.last_message.body', 'Hello!')
            ->assertJsonPath('chats.0.unread_count', 1);
    }
}
