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
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            // The last job alert email read, and the site it came from.
            $table->timestamp('last_alert_at')->nullable()->after('last_discovery_at');
            $table->string('last_alert_source')->nullable()->after('last_alert_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn(['last_alert_at', 'last_alert_source']);
        });
    }
};
