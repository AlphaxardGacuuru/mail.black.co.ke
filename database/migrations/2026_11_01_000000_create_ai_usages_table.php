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
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();
            // Null for system-wide requests that no single user asked for,
            // such as the careers page searches shared between users.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->nullableUuidMorphs('subject');
            $table->string('feature');
            $table->string('provider')
                ->nullable();
            $table->string('model')
                ->nullable();
            $table->unsignedTinyInteger('attempt')
                ->nullable();
            $table->unsignedInteger('input_tokens')
                ->default(0);
            $table->unsignedInteger('cached_input_tokens')
                ->default(0);
            $table->unsignedInteger('output_tokens')
                ->default(0);
            $table->unsignedInteger('reasoning_tokens')
                ->default(0);
            $table->unsignedBigInteger('cost_micros')
                ->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
