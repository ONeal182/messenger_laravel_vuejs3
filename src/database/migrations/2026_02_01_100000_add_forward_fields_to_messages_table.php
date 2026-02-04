<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('forward_from_message_id')
                ->nullable()
                ->after('deleted_for_all_at')
                ->constrained('messages')
                ->nullOnDelete();

            $table->foreignId('forward_from_user_id')
                ->nullable()
                ->after('forward_from_message_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('forward_from_chat_id')
                ->nullable()
                ->after('forward_from_user_id')
                ->constrained('chats')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['forward_from_message_id']);
            $table->dropForeign(['forward_from_user_id']);
            $table->dropForeign(['forward_from_chat_id']);
            $table->dropColumn([
                'forward_from_message_id',
                'forward_from_user_id',
                'forward_from_chat_id',
            ]);
        });
    }
};
