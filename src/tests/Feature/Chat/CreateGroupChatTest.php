<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateGroupChatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_create_group_chat_with_members(): void
    {
        $owner = User::factory()->create(['nickname' => 'owner']);
        $alice = User::factory()->create(['nickname' => 'alice']);
        $bob = User::factory()->create(['nickname' => 'bob']);

        $payload = [
            'title' => 'Команда проекта',
            'nicknames' => ['alice', 'bob'],
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/chats/group', $payload)
            ->assertCreated()
            ->assertJsonPath('chat.type', 'group')
            ->assertJsonCount(3, 'chat.users');
    }

    #[Test]
    public function returns_422_when_members_not_found(): void
    {
        $owner = User::factory()->create(['nickname' => 'owner']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/chats/group', [
                'title' => 'Empty group',
                'nicknames' => ['ghost'],
            ])
            ->assertStatus(422);
    }
}
