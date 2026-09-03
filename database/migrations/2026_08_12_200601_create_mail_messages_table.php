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
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_thread_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('direction'); // inbound, outbound
            $table->string('folder')->default('inbox'); // inbox, sent, draft, trash, archive, spam
            $table->jsonb('folder_history')->nullable();
            $table->jsonb('from_address')->nullable();
            $table->jsonb('to')->nullable();
            $table->jsonb('cc')->nullable();
            $table->jsonb('bcc')->nullable();
            $table->jsonb('reply_to')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('snippet', 160)->nullable();
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('mailgun_message_id')->nullable();
            $table->string('status')->nullable(); // queued, sent, delivered, opened, clicked, failed, bounced, received
            $table->text('error_message')->nullable();
            $table->string('job_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->jsonb('headers')->nullable();
            $table->longText('search_index')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'folder', 'created_at']);
            $table->index(['user_id', 'message_id']);
            $table->index(['user_id', 'in_reply_to']);
            $table->index('mail_thread_id');
            $table->index('mailgun_message_id');
            $table->fullText('search_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mail_messages');
    }
};
