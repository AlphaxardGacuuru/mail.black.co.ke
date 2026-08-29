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
        Schema::create('mailgun_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_event_id')->unique();
            $table->foreignUuid('mail_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('status')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index(['mail_message_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailgun_events');
    }
};
