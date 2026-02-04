<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowChatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_open_his_chat(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $chat = app(ChatService::class)
            ->createPrivateChat($user, $other);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}")
            ->assertOk()
            ->assertJsonPath('chat.id', $chat->id);
    }

    #[Test]
    public function user_cannot_open_chat_he_is_not_member_of(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $other = User::factory()->create();

        $chat = app(ChatService::class)
            ->createPrivateChat($other, $stranger);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}")
            ->assertForbidden();
    }
}
