<?php

namespace Tests\Feature\Message;

use App\Models\User;
use App\Services\ChatService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkReadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_mark_own_message_as_read(): void
    {
        $author = User::factory()->create();
        $recipient = User::factory()->create();

        $chat = app(ChatService::class)->createPrivateChat($author, $recipient);

        $message = app(MessageService::class)->sendMessage($chat, $author, 'hello');

        // Автор пытается отметить своё сообщение
        $this->actingAs($author, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/read", ['message_id' => $message->id])
            ->assertNoContent();

        // pivot автора не должен обновиться
        $pivot = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $author->id)
            ->first();

        $this->assertNull($pivot->last_read_message_id);
    }

    #[Test]
    public function recipient_marks_incoming_message_as_read(): void
    {
        $author = User::factory()->create();
        $recipient = User::factory()->create();

        $chat = app(ChatService::class)->createPrivateChat($author, $recipient);
        $message = app(MessageService::class)->sendMessage($chat, $author, 'hello');

        $this->actingAs($recipient, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/read", ['message_id' => $message->id])
            ->assertNoContent();

        $pivot = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', $recipient->id)
            ->first();

        $this->assertEquals($message->id, $pivot->last_read_message_id);
    }
}
