<?php

namespace Tests\Feature\Message;

use App\Models\User;
use App\Services\ChatService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_hide_message_for_self(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $chat = app(ChatService::class)->createPrivateChat($author, $reader);
        $message = app(MessageService::class)->sendMessage($chat, $author, 'bye');

        $this->actingAs($reader, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertNoContent();

        $exists = DB::table('message_user_deletions')
            ->where('message_id', $message->id)
            ->where('user_id', $reader->id)
            ->exists();

        $this->assertTrue($exists);
    }

    #[Test]
    public function only_author_can_delete_for_all(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $chat = app(ChatService::class)->createPrivateChat($author, $other);
        $message = app(MessageService::class)->sendMessage($chat, $author, 'secret');

        // не автор — 403
        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}/all")
            ->assertStatus(403);

        // автор — успех
        $this->actingAs($author, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}/all")
            ->assertNoContent();

        $this->assertNotNull($message->fresh()->deleted_for_all_at);
    }
}
