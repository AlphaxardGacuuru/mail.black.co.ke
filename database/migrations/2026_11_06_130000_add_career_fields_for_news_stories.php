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
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            // Keys of config('job_search.news.fields') the CV is tagged
            // with, and when, so a new CV is tagged again.
            $table->json('career_fields')->nullable();
            $table->timestamp('career_fields_at')->nullable();
        });

        Schema::table('stories', function (Blueprint $table) {
            // A news story's field: only people tagged with it see it.
            // Stories without one are for everyone.
            $table->string('career_field')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('career_field');
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn(['career_fields', 'career_fields_at']);
        });
    }
};
