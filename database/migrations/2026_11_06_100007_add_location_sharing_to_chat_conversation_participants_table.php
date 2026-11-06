<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversation_participants', function (Blueprint $table) {
            // A persistent opt-in toggle, not a timed event — one row already
            // exists per (conversation, user) here, so sharing state lives on
            // it directly rather than in a separate table.
            $table->boolean('is_sharing_location')->default(false);
            $table->decimal('location_latitude', 10, 7)->nullable();
            $table->decimal('location_longitude', 10, 7)->nullable();
            $table->float('location_accuracy_meters')->nullable();
            $table->timestamp('location_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversation_participants', function (Blueprint $table) {
            $table->dropColumn([
                'is_sharing_location',
                'location_latitude',
                'location_longitude',
                'location_accuracy_meters',
                'location_updated_at',
            ]);
        });
    }
};
