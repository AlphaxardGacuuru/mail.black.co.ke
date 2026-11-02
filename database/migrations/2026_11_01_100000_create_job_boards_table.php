<?php

use App\Models\JobPosting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('job_boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('host')->unique();
            // Where job links are listed: an RSS/Atom feed, a sitemap (or
            // sitemap index) or an HTML page.
            $table->string('listing_url', 2048);
            // Only links matching this regular expression are job pages.
            $table->string('link_pattern', 500);
            // "structured" reads schema.org JobPosting data; "myjobmag" is
            // myjobmag's own page layout; "ai" has the AI read each page.
            $table->string('reader')->default('structured');
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_listed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->foreignUuid('job_board_id')
                ->nullable()
                ->after('source')
                ->constrained()
                ->onDelete('cascade');
            // The same job posted on several boards shares this key, so it's
            // only matched once.
            $table->char('dedupe_key', 64)->nullable()->after('fingerprint');
            $table->timestamp('valid_through')->nullable()->after('posted_at');

            $table->index('dedupe_key');
        });

        $now = now();
        $boards = [
            [
                'name' => 'MyJobMag Kenya',
                'host' => 'www.myjobmag.co.ke',
                'listing_url' => 'https://www.myjobmag.co.ke/jobsxml.xml',
                'link_pattern' => '#^https://www\.myjobmag\.co\.ke/jobs/[^?]+$#',
                'reader' => 'myjobmag',
            ],
            [
                'name' => 'BrighterMonday Kenya',
                'host' => 'www.brightermonday.co.ke',
                'listing_url' => 'https://www.brightermonday.co.ke/sitemap-listings-index-en.xml',
                'link_pattern' => '#^https://www\.brightermonday\.co\.ke/listings/[^?/]+$#',
                'reader' => 'structured',
            ],
        ];

        DB::table('job_boards')->insert(collect($boards)
            ->map(fn (array $board) => [
                ...$board,
                'id' => (string) Str::uuid(),
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all());

        // Postings read before boards existed all came from myjobmag.
        $myJobMag = DB::table('job_boards')
            ->where('host', 'www.myjobmag.co.ke')
            ->value('id');

        DB::table('job_postings')
            ->where('source', 'myjobmag')
            ->orderBy('id')
            ->each(fn (object $posting) => DB::table('job_postings')
                ->where('id', $posting->id)
                ->update([
                    'source' => JobPosting::SOURCE_BOARD,
                    'job_board_id' => $myJobMag,
                    'fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_BOARD, $posting->url),
                    'dedupe_key' => JobPosting::dedupeKey($posting->company, $posting->title),
                ]));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('job_postings')
            ->where('source', JobPosting::SOURCE_BOARD)
            ->update(['source' => 'myjobmag']);

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_board_id');
            $table->dropIndex(['dedupe_key']);
            $table->dropColumn(['dedupe_key', 'valid_through']);
        });

        Schema::dropIfExists('job_boards');
    }
};
