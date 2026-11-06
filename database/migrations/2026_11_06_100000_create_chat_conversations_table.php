<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('direct'); // direct, group (group support later)
            // Only ever set for type=direct (a deterministic, sorted
            // "userA:userB" key). The unique index is what actually
            // guarantees at most one direct conversation per pair under
            // concurrent requests, not just the application-level lookup.
            // NULL for group conversations, and MySQL allows multiple NULLs
            // in a unique index so those never collide with each other.
            $table->string('pair_key')->nullable()->unique();
            $table->string('name')->nullable(); // group conversations only
            // Microsecond precision: compared against a participant's
            // delete cutoff (chat_conversation_participants.deleted_at) to
            // decide whether the conversation is visible to them — the
            // default second precision can make two events milliseconds
            // apart compare as equal instead of ordered.
            $table->timestamp('last_message_at', 6)->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
