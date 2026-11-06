<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversation_participants', function (Blueprint $table) {
            // Plain auto-increment id: rows here are only ever created via
            // the participants() BelongsToMany's attach(), which does a raw
            // bulk insert and never generates a HasUuids id itself.
            $table->id();
            $table->foreignUuid('conversation_id')
                ->constrained('chat_conversations')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // Microsecond precision throughout: last_read_at/archived_at
            // are only ever compared for ordering/nullness in PHP, but
            // deleted_at is compared against chat_messages.created_at (and
            // chat_conversations.last_message_at) to decide what a
            // participant can see after they've deleted a conversation —
            // the default second precision can make two events
            // milliseconds apart compare as equal instead of ordered.
            $table->timestamp('last_read_at', 6)->nullable();
            // Toggled on/off; hides the conversation from this user's index
            // with no history implications.
            $table->timestamp('archived_at', 6)->nullable();
            // A per-participant visibility cutoff, not a boolean hide:
            // everything up to this point is gone from this user's view,
            // but it isn't a dead end — a message landing after the cutoff
            // (from either side) brings the conversation back for them,
            // starting from that message. See ChatConversation::
            // scopeNotDeletedBy() and ChatConversationService::show().
            $table->timestamp('deleted_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_participants');
    }
};
