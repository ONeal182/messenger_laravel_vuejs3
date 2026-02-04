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
        Schema::table('chats', function (Blueprint $table) {
            if (! Schema::hasColumn('chats', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
        });

        // убрать ошибочный столбец из pivot, если он есть
        Schema::table('chat_user', function (Blueprint $table) {
            if (Schema::hasColumn('chat_user', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'title')) {
                $table->dropColumn('title');
            }
        });

        Schema::table('chat_user', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_user', 'title')) {
                $table->string('title')->nullable()->after('user_id');
            }
        });
    }
};
