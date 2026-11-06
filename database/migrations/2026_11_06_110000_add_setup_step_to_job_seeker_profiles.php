<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the user left the job search setup wizard, to pick up from.
     */
    public function up(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->string('setup_step')->nullable()->after('consented_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn('setup_step');
        });
    }
};
