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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source');
            // Set for postings found on a user's own company pages, which
            // are only matched for that user; null for public boards.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('company_career_page_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');
            $table->char('fingerprint', 64)->unique();
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->string('industry')->nullable();
            $table->mediumText('description')->nullable();
            $table->string('url', 2048);
            $table->string('apply_email')->nullable();
            $table->text('apply_instructions')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('details_fetched_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'details_fetched_at']);
            $table->index('posted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('job_postings');
    }
};
