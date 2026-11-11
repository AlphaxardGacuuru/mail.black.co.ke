<?php

use App\Models\JobBoard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recent myjobmag pages that list several jobs were read as one job:
     * they're read again, so each job on them becomes its own posting.
     */
    public function up(): void
    {
        DB::table('job_postings')
            ->whereIn('job_board_id', DB::table('job_boards')
                ->where('reader', JobBoard::READER_MYJOBMAG)
                ->select('id'))
            ->where('apply_instructions', 'like', 'Use the link(s) below%')
            ->where('posted_at', '>=', now()->subDays(config('job_search.max_posting_age_days')))
            ->update(['details_fetched_at' => null]);
    }

    public function down(): void
    {
        //
    }
};
