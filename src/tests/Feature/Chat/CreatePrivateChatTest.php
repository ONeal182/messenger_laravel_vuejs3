<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreatePrivateChatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_private_chat_between_two_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $service = app(ChatService::class);

        $chat = $service->createPrivateChat($userA, $userB);

        // Проверяем чат
        $this->assertEquals('private', $chat->type);

        // Проверяем pivot
        $this->assertCount(2, $chat->users);

        $this->assertTrue(
            $chat->users->contains('id', $userA->id)
        );

        $this->assertTrue(
            $chat->users->contains('id', $userB->id)
        );
    }

    #[Test]
    public function it_does_not_create_duplicate_private_chats(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $service = app(ChatService::class);

        $chat1 = $service->createPrivateChat($userA, $userB);
        $chat2 = $service->createPrivateChat($userA, $userB);

        $this->assertTrue($chat1->is($chat2));
        $this->assertEquals(1, \App\Models\Chat::count());
    }
}
