<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('job_posting_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('status');
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->string('match_reason', 500)->nullable();
            $table->string('subject')->nullable();
            $table->text('cover_letter')->nullable();
            $table->foreignUuid('mail_message_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignUuid('mail_thread_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_posting_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'applied_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('job_applications');
    }
};
