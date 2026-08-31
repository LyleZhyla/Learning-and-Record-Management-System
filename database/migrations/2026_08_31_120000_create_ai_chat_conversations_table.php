<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('user_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
        });

        DB::table('ai_chat_messages')->select('user_id')->distinct()->orderBy('user_id')->each(function ($row): void {
            $firstMessage = DB::table('ai_chat_messages')->where('user_id', $row->user_id)->oldest()->value('content');
            $now = now();
            $conversationId = DB::table('ai_chat_conversations')->insertGetId([
                'user_id' => $row->user_id,
                'title' => str($firstMessage ?: 'Previous conversation')->squish()->limit(55),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('ai_chat_messages')->where('user_id', $row->user_id)->update(['conversation_id' => $conversationId]);
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::dropIfExists('ai_chat_conversations');
    }
};
