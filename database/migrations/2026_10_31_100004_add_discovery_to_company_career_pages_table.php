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
        Schema::table('company_career_pages', function (Blueprint $table) {
            // "user" when added by hand, "search" when found by web search.
            $table->string('source')->default('user')->after('url_hash');
            $table->string('host')->nullable()->after('source');
            // Found pages the user removed (or that weren't careers pages)
            // are kept, hidden, so the search never adds them again.
            $table->timestamp('dismissed_at')->nullable()->after('last_error');

            $table->index(['user_id', 'host']);
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->timestamp('last_discovery_at')->nullable()->after('parsed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('company_career_pages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'host']);
            $table->dropColumn(['source', 'host', 'dismissed_at']);
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn('last_discovery_at');
        });
    }
};
