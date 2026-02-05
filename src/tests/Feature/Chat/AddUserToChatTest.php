<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddUserToChatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function member_can_add_user_by_nickname(): void
    {
        $owner = User::factory()->create(['nickname' => 'owner']);
        $member = User::factory()->create(['nickname' => 'member']);
        $newUser = User::factory()->create(['nickname' => 'newbie']);

        $chat = app(ChatService::class)->createPrivateChat($owner, $member);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/users", ['nickname' => $newUser->nickname])
            ->assertOk()
            ->assertJsonPath('chat.users.*.nickname', ['owner', 'member', 'newbie']);
    }

    #[Test]
    public function non_member_cannot_add_user(): void
    {
        $owner = User::factory()->create(['nickname' => 'owner']);
        $member = User::factory()->create(['nickname' => 'member']);
        $stranger = User::factory()->create(['nickname' => 'stranger']);
        $newUser = User::factory()->create(['nickname' => 'newbie']);

        $chat = app(ChatService::class)->createPrivateChat($owner, $member);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/users", ['nickname' => $newUser->nickname])
            ->assertStatus(403);
    }
}
