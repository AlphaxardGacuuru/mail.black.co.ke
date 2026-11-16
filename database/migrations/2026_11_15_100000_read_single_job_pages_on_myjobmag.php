<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Myjobmag's job alerts link to a single job's page (/job/...), beside
     * the /jobs/... pages its feed lists: both are read as its jobs.
     */
    public function up(): void
    {
        DB::table('job_boards')
            ->where('host', 'www.myjobmag.co.ke')
            ->update(['link_pattern' => '#^https://www\.myjobmag\.co\.ke/jobs?/[^?]+$#']);
    }

    public function down(): void
    {
        DB::table('job_boards')
            ->where('host', 'www.myjobmag.co.ke')
            ->update(['link_pattern' => '#^https://www\.myjobmag\.co\.ke/jobs/[^?]+$#']);
    }
};
