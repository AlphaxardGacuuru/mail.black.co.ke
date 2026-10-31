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
        Schema::create('job_seeker_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('mailgun_account_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->boolean('auto_apply')->default(false);
            $table->string('mode')->default('review');
            $table->timestamp('consented_at')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('cv_mime_type')->nullable();
            $table->char('cv_hash', 64)->nullable();
            $table->json('profile')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->json('preferences')->nullable();
            $table->unsignedTinyInteger('daily_limit')->default(5);
            $table->unsignedTinyInteger('min_match_score')->default(75);
            $table->string('paused_reason')->nullable();
            $table->timestamps();

            $table->index('auto_apply');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('job_seeker_profiles');
    }
};
