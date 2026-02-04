<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListChatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_get_his_chats(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $service = app(ChatService::class);

        $service->createPrivateChat($user, $other);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonCount(1, 'chats');
    }
}
