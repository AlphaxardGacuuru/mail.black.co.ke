<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')
                ->constrained('chat_conversations')
                ->cascadeOnDelete();
            $table->foreignUuid('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('reply_to_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();
            $table->text('body')->nullable();
            // Microsecond precision: created_at is compared against a
            // participant's delete cutoff (chat_conversation_participants.
            // deleted_at) to decide what's visible to them — the default
            // second precision can make two events milliseconds apart
            // compare as equal instead of ordered.
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
