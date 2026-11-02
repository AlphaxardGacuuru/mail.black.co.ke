<?php

use App\Enums\JobApplicationStatus;
use App\Jobs\DeleteStaleTemporaryUploadsJob;
use App\Jobs\DiscoverCareerPagesJob;
use App\Jobs\FetchJobPostingsJob;
use App\Jobs\MatchJobsForUserJob;
use App\Jobs\SendJobApplicationsJob;
use App\Jobs\SyncCareerPageJob;
use App\Models\CompanyCareerPage;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune Telescope entries older than 30 days.
// Daily at 00:00 EAT.
Schedule::command('telescope:prune --hours=720')->daily();

// TODO: `websockets:clean` came from the old laravel-websockets package,
// which this project no longer uses (replaced by Reverb). Remove or swap
// in a Reverb-equivalent cleanup task, then re-enable.
// Schedule::command('websockets:clean')->weekly();

// Daily at 01:00 EAT.
Schedule::job(new DeleteStaleTemporaryUploadsJob)
    // ->everyMinute();
    ->dailyAt("01:00");

// ─── Job search ───────────────────────────────────────────────────────────────
// Find jobs, match them, then send: each step only runs for users who have
// job search on, and sending re-checks every guardrail.

// Hourly, at 5 minutes past the hour.
Schedule::job(new FetchJobPostingsJob)->hourlyAt(5)->withoutOverlapping();

// Daily at 05:00 EAT, for pages not checked in the last 20 hours.
Schedule::call(function () {
    CompanyCareerPage::query()
        ->active()
        ->whereHas('user.jobSeekerProfile', fn ($query) => $query->where('auto_apply', true))
        ->where(fn ($query) => $query->whereNull('last_fetched_at')->orWhere('last_fetched_at', '<=', now()->subHours(20)))
        ->pluck('id')
        ->each(fn (string $id) => SyncCareerPageJob::dispatch($id));
})->name('sync-career-pages')->dailyAt('05:00');

// Search the web for employers' careers pages, once a week per user.
// Runs daily at 04:30 EAT and picks up users whose last search
// was at least job_search.discovery.interval_days (7) days ago.
Schedule::call(function () {
    JobSeekerProfile::query()
        ->where('auto_apply', true)
        ->whereNotNull('parsed_at')
        ->where(fn ($query) => $query
            ->whereNull('last_discovery_at')
            ->orWhere('last_discovery_at', '<=', now()->subDays(config('job_search.discovery.interval_days'))))
        ->pluck('id')
        ->each(fn (string $id) => DiscoverCareerPagesJob::dispatch($id));
})->name('discover-career-pages')->dailyAt('04:30');

// Hourly, at 25 minutes past the hour.
Schedule::call(function () {
    JobSeekerProfile::query()
        ->where('auto_apply', true)
        ->whereNotNull('parsed_at')
        ->pluck('id')
        ->each(fn (string $id) => MatchJobsForUserJob::dispatch($id));
})->name('match-jobs')->hourlyAt(25);

// Hourly, at 45 minutes past the hour.
Schedule::job(new SendJobApplicationsJob)->hourlyAt(45)->withoutOverlapping();

// Postings nobody acted on are dropped after 60 days.
// Daily at 03:00 EAT.
Schedule::call(function () {
    JobPosting::query()
        ->where('created_at', '<', now()->subDays(60))
        ->whereDoesntHave('applications', fn ($query) => $query->where('status', '!=', JobApplicationStatus::NOT_MATCHED))
        ->delete();
})->name('prune-job-postings')->dailyAt('03:00');
