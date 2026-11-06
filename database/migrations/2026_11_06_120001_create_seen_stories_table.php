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
        Schema::create('seen_stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')
                ->constrained('stories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seen_stories');
    }
};
