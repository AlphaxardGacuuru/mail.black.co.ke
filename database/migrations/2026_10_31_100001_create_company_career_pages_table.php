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
        Schema::create('company_career_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('url', 2048);
            $table->char('url_hash', 64);
            $table->string('company_name')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'url_hash']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_career_pages');
    }
};
