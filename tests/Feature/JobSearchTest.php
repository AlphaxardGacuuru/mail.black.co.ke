<?php

namespace Tests\Feature;

use App\Agents\CareerPageAgent;
use App\Agents\CareerPageSearchAgent;
use App\Agents\CvProfileAgent;
use App\Agents\JobAlertAgent;
use App\Agents\JobApplicationAgent;
use App\Agents\JobMatchAgent;
use App\Agents\JobPageAgent;
use App\Enums\JobApplicationStatus;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Jobs\FetchJobBoardListingJob;
use App\Jobs\FetchJobPostingDetailsJob;
use App\Jobs\FetchJobPostingsJob;
use App\Jobs\ParseCvJob;
use App\Jobs\ReadJobAlertJob;
use App\Jobs\SendMailMessageJob;
use App\Jobs\SyncCareerPageJob;
use App\Models\AiUsage;
use App\Models\CompanyCareerPage;
use App\Models\JobApplication;
use App\Models\JobBoard;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\User;
use App\Notifications\JobApplicationFailedNotification;
use App\Notifications\JobApplicationRepliedNotification;
use App\Notifications\JobApplicationSentNotification;
use App\Notifications\JobApplicationsReadyNotification;
use App\Notifications\JobSearchPausedNotification;
use App\Notifications\JobsToApplyManuallyNotification;
use App\Services\Jobs\CareerPageDiscovery;
use App\Services\Jobs\CompanyCareerPageSource;
use App\Services\Jobs\JobAlertReader;
use App\Services\Jobs\JobApplicationSender;
use App\Services\Jobs\JobBoardSource;
use App\Services\Jobs\JobMatcher;
use App\Services\Jobs\SafePageFetcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use NotificationChannels\WebPush\WebPushChannel;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class JobSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MailgunAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Every host resolves to a public address without real DNS.
        $this->app->instance(SafePageFetcher::class, new class extends SafePageFetcher
        {
            protected function resolve(string $host): array
            {
                return $host === 'intranet.example.com' ? ['10.0.0.5'] : ['93.184.216.34'];
            }
        });

        $this->user = User::factory()->create(['name' => 'Wanjiku Muhandi']);
        $domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'example.com', 'api_key' => 'key']);
        $this->account = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => 'wanjiku@example.com',
        ]);
        $this->user->update(['active_mailgun_account_id' => $this->account->id]);
    }

    private function readyProfile(array $overrides = []): JobSeekerProfile
    {
        Storage::disk('local')->put('job-search/cv/wanjiku.pdf', '%PDF-1.4 fake');

        return JobSeekerProfile::create([
            'user_id' => $this->user->id,
            'auto_apply' => true,
            'mode' => 'review',
            'consented_at' => now(),
            'cv_path' => 'job-search/cv/wanjiku.pdf',
            'cv_original_name' => 'Wanjiku CV.pdf',
            'cv_hash' => str_repeat('a', 64),
            'parsed_at' => now(),
            'profile' => [
                'full_name' => 'Wanjiku Muhandi',
                'headline' => 'Accountant with 6 years in audit',
                'summary' => 'CPA(K) accountant.',
                'years_of_experience' => 6,
                'skills' => ['IFRS', 'Audit'],
                'target_titles' => ['Accountant', 'Finance Officer'],
                'education' => ['BCom, University of Nairobi'],
                'experience' => [['title' => 'Auditor', 'company' => 'KPMG', 'period' => '2019-2025']],
                'location' => 'Nairobi',
            ],
            'preferences' => ['keywords' => [], 'locations' => ['Nairobi'], 'remote_ok' => false, 'excluded_companies' => ['Bad Corp']],
            'daily_limit' => 5,
            'min_match_score' => 75,
            ...$overrides,
        ]);
    }

    private function posting(string $title, ?string $company = 'Acme Ltd', ?string $email = 'jobs@acme.co.ke', array $overrides = []): JobPosting
    {
        return JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', $title, (string) $company),
            'title' => $title,
            'company' => $company,
            'location' => 'Nairobi',
            'description' => "We need a {$title} with IFRS experience.",
            'url' => 'https://www.myjobmag.co.ke/jobs/'.str($title)->slug(),
            'apply_email' => $email,
            'posted_at' => now()->subDay(),
            'details_fetched_at' => now(),
            ...$overrides,
        ]);
    }

    private function queuedApplication(JobPosting $posting, array $overrides = []): JobApplication
    {
        return JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => $posting->id,
            'status' => JobApplicationStatus::QUEUED,
            'match_score' => 90,
            'subject' => "Application for {$posting->title}",
            'cover_letter' => "Dear Hiring Manager,\n\nI'd like to apply.\n\nKind regards,\nWanjiku Muhandi",
            ...$overrides,
        ]);
    }

    /** Wednesday 10:00 in Nairobi. */
    private function duringWorkingHours(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Africa/Nairobi'));
    }

    // ─── Sources ─────────────────────────────────────────────────────────────

    public function test_myjobmag_feed_and_job_pages_are_read(): void
    {
        Http::fake([
            'www.myjobmag.co.ke/robots.txt' => Http::response("User-agent: *\nDisallow: /*?\nDisallow: /apply-now/\n"),
            'www.myjobmag.co.ke/jobsxml.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="iso-8859-1"?><rss version="2.0"><channel>
                <item><title><![CDATA[Accountant at Acme Ltd]]></title><industry><![CDATA[Finance]]></industry>
                <link><![CDATA[https://www.myjobmag.co.ke/jobs/accountant-at-acme-ltd]]></link>
                <pubDate>Fri, 30 Oct 2026 15:43:30 GMT</pubDate><description><![CDATA[Acme makes things.]]></description></item>
                <item><title><![CDATA[Ignored]]></title><link><![CDATA[https://www.myjobmag.co.ke/a_fields.php?id=1]]></link></item>
                </channel></rss>
                XML),
            'www.myjobmag.co.ke/jobs/accountant-at-acme-ltd' => Http::response(<<<'HTML'
                <html><body>
                <ul class="job-key-info">
                <li><span class="jkey-title">Job Type</span> <span class="jkey-info"><a href="/jobs-by-type/full-time">Full Time</a></span></li>
                <li><span class="jkey-title">Location</span> <span class="jkey-info"><a href='/jobs-location/nairobi'>Nairobi</a></span></li>
                <li><span class="jkey-title">Job Field</span> <span class="jkey-info"><a href="/jobs-by-field/finance">Finance</a>&nbsp;</span></li>
                </ul>
                <div class="job-details"><p>Key Responsibilities</p><p>Prepare IFRS accounts.</p></div>
                <h2 id="application-method">Method of Application</h2>
                <div><p>Send your CV to <strong>Careers@Acme.co.ke</strong> quoting ACC-01.</p></div>
                </body></html>
                HTML),
        ]);

        $source = app(JobBoardSource::class);
        $board = JobBoard::where('host', 'www.myjobmag.co.ke')->sole();

        $this->assertSame(1, $source->list($board, $board->listing_url)['created']);

        $posting = JobPosting::sole();
        $this->assertSame('Accountant', $posting->title);
        $this->assertSame('Acme Ltd', $posting->company);
        $this->assertSame($board->id, $posting->job_board_id);

        $this->assertTrue($source->fetchDetails($posting));
        $posting->refresh();

        $this->assertSame('careers@acme.co.ke', $posting->apply_email);
        $this->assertSame('Nairobi', $posting->location);
        $this->assertStringContainsString('Prepare IFRS accounts.', $posting->description);
        $this->assertStringContainsString('ACC-01', $posting->apply_instructions);
        $this->assertNotNull($posting->details_fetched_at);
    }

    public function test_pages_disallowed_by_robots_txt_are_not_fetched(): void
    {
        Http::fake([
            'jobs.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /careers\n"),
            '*' => Http::response('should not be fetched'),
        ]);

        $this->expectException(RuntimeException::class);

        app(SafePageFetcher::class)->fetch('https://jobs.example.org/careers/open');
    }

    public function test_career_pages_on_private_networks_are_refused(): void
    {
        foreach (['http://127.0.0.1/admin', 'http://intranet.example.com/jobs', 'ftp://example.com/jobs', 'http://example.com:8080/'] as $url) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/job-search/career-pages', ['url' => $url])
                ->assertUnprocessable();
        }

        $this->assertSame(0, CompanyCareerPage::count());
    }

    public function test_career_page_jobs_keep_only_emails_found_on_the_page_and_unchanged_pages_skip_the_ai(): void
    {
        Http::fake([
            'acme.co.ke/robots.txt' => Http::response('', 404),
            'acme.co.ke/careers' => Http::response('<html><body><h2>Finance Officer</h2><p>Apply: hr@acme.co.ke</p><h2>Driver</h2></body></html>'),
        ]);
        CareerPageAgent::fake([[
            'company' => 'Acme',
            'jobs' => [
                ['title' => 'Finance Officer', 'location' => 'Nairobi', 'description' => 'IFRS', 'apply_email' => 'hr@acme.co.ke', 'apply_instructions' => '', 'url' => ''],
                ['title' => 'Driver', 'location' => 'Nairobi', 'description' => 'Driving', 'apply_email' => 'invented@acme.co.ke', 'apply_instructions' => '', 'url' => 'https://evil.example/apply'],
            ],
        ]]);

        $page = CompanyCareerPage::create(['user_id' => $this->user->id, 'url' => 'https://acme.co.ke/careers', 'url_hash' => hash('sha256', 'https://acme.co.ke/careers')]);
        $source = app(CompanyCareerPageSource::class);

        $this->assertSame(2, $source->sync($page));
        $this->assertSame('hr@acme.co.ke', JobPosting::where('title', 'Finance Officer')->value('apply_email'));
        $this->assertNull(JobPosting::where('title', 'Driver')->value('apply_email'));
        $this->assertSame('https://acme.co.ke/careers', JobPosting::where('title', 'Driver')->value('url'));
        $this->assertSame($this->user->id, JobPosting::where('title', 'Driver')->value('user_id'));

        $this->assertSame(0, $source->sync($page->fresh()));
        CareerPageAgent::assertPrompted(fn () => true);
        $this->assertSame(2, JobPosting::count());
    }

    // ─── Job boards ──────────────────────────────────────────────────────────

    private function aiBoard(): JobBoard
    {
        return JobBoard::create([
            'name' => 'Jobs Blog',
            'host' => 'jobs.example.org',
            'listing_url' => 'https://jobs.example.org/feed/',
            'link_pattern' => '#^https://jobs\.example\.org/[^?]+$#',
            'reader' => JobBoard::READER_AI,
            'enabled' => true,
        ]);
    }

    private function boardPosting(JobBoard $board, string $path, string $title = ''): JobPosting
    {
        return JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'job_board_id' => $board->id,
            'fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_BOARD, "https://{$board->host}/{$path}"),
            'title' => $title,
            'url' => "https://{$board->host}/{$path}",
            'posted_at' => now()->subDay(),
        ]);
    }

    public function test_boards_with_structured_job_data_are_read_through_their_sitemaps(): void
    {
        $job = json_encode(['@context' => 'https://schema.org', '@graph' => [
            // The board's own office, which it also puts on every listing.
            ['@type' => 'Organization', 'name' => 'BrighterMonday Kenya', 'url' => 'https://www.brightermonday.co.ke', 'address' => ['streetAddress' => 'Riverside Drive', 'addressRegion' => 'Westlands', 'addressCountry' => 'Kenya']],
            [
                '@type' => 'JobPosting',
                'title' => 'Accountant',
                'description' => '<p>Prepare IFRS accounts.</p><p><b>How to Apply</b></p><p>Send your CV to:<br><b>HR@Acme.co.ke</b>. Questions: csteam@brightermonday.co.ke</p>',
                'datePosted' => now()->subDays(2)->toIso8601String(),
                'validThrough' => now()->addMonth()->toIso8601String(),
                'hiringOrganization' => ['@id' => '#acme'],
                'jobLocation' => ['@id' => '#place'],
            ],
            ['@type' => 'Organization', '@id' => '#acme', 'name' => 'Acme Ltd'],
            ['@type' => 'Place', '@id' => '#place', 'address' => ['@id' => '#address']],
            ['@type' => 'PostalAddress', '@id' => '#address', 'streetAddress' => 'Nairobi', 'addressRegion' => 'Westlands', 'addressCountry' => 'Kenya'],
        ]]);
        $recent = now()->subDay()->toAtomString();
        $old = now()->subDays(60)->toAtomString();

        Http::fake([
            'www.brightermonday.co.ke/robots.txt' => Http::response("User-agent: *\nDisallow: /job/\n"),
            'www.brightermonday.co.ke/sitemap-listings-index-en.xml' => Http::response(<<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <sitemap><loc>https://www.brightermonday.co.ke/sitemap-listings-finance-en.xml</loc><lastmod>{$recent}</lastmod></sitemap>
                <sitemap><loc>https://elsewhere.example.com/sitemap.xml</loc><lastmod>{$recent}</lastmod></sitemap>
                </sitemapindex>
                XML),
            'www.brightermonday.co.ke/sitemap-listings-finance-en.xml' => Http::response(<<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://www.brightermonday.co.ke/listings/accountant-abc123</loc><lastmod>{$recent}</lastmod></url>
                <url><loc>https://www.brightermonday.co.ke/listings/driver-old999</loc><lastmod>{$old}</lastmod></url>
                <url><loc>https://www.brightermonday.co.ke/about</loc><lastmod>{$recent}</lastmod></url>
                </urlset>
                XML),
            'www.brightermonday.co.ke/listings/accountant-abc123' => Http::response("<html><head><script type=\"application/ld+json\">{$job}</script></head><body></body></html>"),
        ]);

        $source = app(JobBoardSource::class);
        $board = JobBoard::where('host', 'www.brightermonday.co.ke')->sole();
        $sitemap = 'https://www.brightermonday.co.ke/sitemap-listings-finance-en.xml';

        // The index only names its sitemaps on this board; each is read on its own.
        $this->assertSame([$sitemap => $recent], $source->list($board, $board->listing_url)['sitemaps']);
        $this->assertSame(1, $source->list($board, $sitemap)['created']);

        // Read once, an unchanged sitemap is skipped next time.
        $source->markSitemapRead($sitemap, $recent);
        $this->assertSame([], $source->list($board, $board->listing_url)['sitemaps']);

        // Known only by its link, the job isn't matched until its page is read.
        $posting = JobPosting::sole();
        $this->assertSame(0, JobPosting::availableTo($this->user->id)->count());

        $this->assertTrue($source->fetchDetails($posting));
        $posting->refresh();

        $this->assertSame('Accountant', $posting->title);
        $this->assertSame('Acme Ltd', $posting->company);
        $this->assertSame('Nairobi', $posting->location);
        $this->assertSame('hr@acme.co.ke', $posting->apply_email);
        $this->assertStringStartsWith('Send your CV to:', $posting->apply_instructions);
        $this->assertTrue($posting->valid_through->isFuture());
        $this->assertSame(1, JobPosting::availableTo($this->user->id)->count());
    }

    public function test_boards_without_structured_data_are_read_by_the_ai(): void
    {
        // The address as Cloudflare's email protection hides it on the page.
        $hidden = collect(str_split('jobs@acme.co.ke'))
            ->map(fn (string $character) => sprintf('%02x', ord($character) ^ 0x42))
            ->prepend('42')
            ->implode('');

        Http::fake([
            'jobs.example.org/robots.txt' => Http::response('', 404),
            'jobs.example.org/accountant-at-acme' => Http::response(
                '<html><body><nav>Latest jobs menu</nav>'
                .'<article><h2>Related: Driver at Beta</h2></article>'
                ."<article><h1>Accountant at Acme</h1><p>Send CVs to <a href=\"/cdn-cgi/l/email-protection#{$hidden}\"><span class=\"__cf_email__\" data-cfemail=\"{$hidden}\">[email&#160;protected]</span></a></p></article>"
                .'</body></html>'
            ),
            'jobs.example.org/how-to-write-a-cv' => Http::response('<html><body><article><h1>How to write a CV</h1></article></body></html>'),
        ]);
        JobPageAgent::fake([
            ['is_job' => true, 'title' => 'Accountant', 'company' => 'Acme Ltd', 'location' => 'Nairobi', 'description' => 'IFRS accounts.', 'apply_email' => 'jobs@acme.co.ke', 'apply_instructions' => 'Send CVs to jobs@acme.co.ke', 'closing_date' => now()->addWeek()->toDateString()],
            ['is_job' => false, 'title' => '', 'company' => '', 'location' => '', 'description' => '', 'apply_email' => '', 'apply_instructions' => '', 'closing_date' => ''],
        ]);

        $source = app(JobBoardSource::class);
        $board = $this->aiBoard();
        $job = $this->boardPosting($board, 'accountant-at-acme', 'Accountant at Acme');
        $article = $this->boardPosting($board, 'how-to-write-a-cv', 'How to write a CV');

        $this->assertTrue($source->fetchDetails($job));
        $job->refresh();
        $this->assertSame('Acme Ltd', $job->company);
        $this->assertSame('jobs@acme.co.ke', $job->apply_email);
        $this->assertTrue($job->valid_through->isFuture());

        // Only the advert itself is sent, not the menus and related posts
        // around it, and with its hidden address written out.
        JobPageAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Send CVs to jobs@acme.co.ke')
            && ! str_contains($prompt->prompt, 'Latest jobs menu')
            && ! str_contains($prompt->prompt, 'Driver at Beta'));

        // A page that isn't an advert is kept out of matching.
        $this->assertFalse($source->fetchDetails($article));
        $this->assertSame([$job->id], JobPosting::availableTo($this->user->id)->pluck('id')->all());

        // Shared by everyone, so billed to the system.
        $this->assertSame(2, AiUsage::where('feature', AiUsage::FEATURE_JOB_PAGE_READ)->whereNull('user_id')->count());
    }

    public function test_pages_the_ai_reads_stay_within_the_daily_allowance(): void
    {
        Queue::fake();
        config(['job_search.boards.max_ai_pages_per_day' => 2]);
        $this->readyProfile();
        $board = $this->aiBoard();

        foreach (range(1, 5) as $number) {
            $this->boardPosting($board, "job-{$number}");
        }

        (new FetchJobPostingsJob)->handle(app(JobBoardSource::class));

        Queue::assertPushed(FetchJobBoardListingJob::class, 3);
        Queue::assertPushed(FetchJobPostingDetailsJob::class, 2);
    }

    public function test_a_board_is_added_switched_off_once_its_job_pages_are_checked(): void
    {
        $job = json_encode(['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => 'Accountant', 'hiringOrganization' => ['@type' => 'Organization', 'name' => 'Acme Ltd']]);

        Http::fake([
            'jobs.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /admin/\nSitemap: https://jobs.example.org/sitemap-blog.xml\nSitemap: https://jobs.example.org/sitemap-vacancies.xml\n"),
            'jobs.example.org/sitemap-vacancies.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://jobs.example.org/vacancies/accountant-1</loc></url>
                <url><loc>https://jobs.example.org/vacancies/driver-2</loc></url>
                <url><loc>https://jobs.example.org/about</loc></url>
                </urlset>
                XML),
            'jobs.example.org/vacancies/*' => Http::response("<html><head><script type=\"application/ld+json\">{$job}</script></head></html>"),
        ]);

        $this->artisan('job-boards:add', ['url' => 'https://jobs.example.org'])
            ->assertSuccessful();

        $board = JobBoard::where('host', 'jobs.example.org')->sole();

        $this->assertFalse($board->enabled);
        $this->assertSame('https://jobs.example.org/sitemap-vacancies.xml', $board->listing_url);
        $this->assertTrue($board->isJobLink('https://jobs.example.org/vacancies/finance-officer-3'));
        $this->assertFalse($board->isJobLink('https://jobs.example.org/about'));
    }

    // ─── Job alerts ──────────────────────────────────────────────────────────

    private function alertEmail(string $from, string $subject, string $html = ''): MailMessage
    {
        return $this->sentMessage([
            'direction' => 'inbound',
            'folder' => 'inbox',
            'status' => 'received',
            'from_address' => ['address' => $from],
            'to' => [['address' => 'wanjiku@example.com']],
            'subject' => $subject,
            'body_html' => $html,
            'received_at' => now(),
        ]);
    }

    public function test_job_alert_emails_from_job_sites_are_read_when_job_search_is_on(): void
    {
        Queue::fake();
        config(['services.mailgun.webhook_signing_secret' => 'secret']);
        $profile = $this->readyProfile();

        $receive = function (string $from, string $subject) {
            $timestamp = (string) now()->timestamp;
            $token = Str::random(10);

            $this->postJson('/api/webhooks/mailgun', [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac('sha256', $timestamp.$token, 'secret'),
                'recipient' => 'wanjiku@example.com',
                'sender' => $from,
                'from' => $from,
                'To' => 'wanjiku@example.com',
                'subject' => $subject,
                'body-html' => '<p>Hello</p>',
                'Message-Id' => '<'.Str::random(10).'@example.com>',
            ])->assertOk();
        };

        $receive('jobalerts-noreply@linkedin.com', 'Accountant: Acme Ltd and 4 more jobs in Nairobi');
        // Not alerts: LinkedIn's other mail, and mail from anyone else.
        $receive('notifications-noreply@linkedin.com', 'You appeared in 5 searches this week');
        $receive('friend@gmail.com', 'Any jobs going?');

        Queue::assertPushed(ReadJobAlertJob::class, 1);

        $profile->update(['auto_apply' => false]);
        $receive('alert@indeed.com', 'New jobs for accountant');

        Queue::assertPushed(ReadJobAlertJob::class, 1);
    }

    public function test_jobs_in_an_alert_join_a_board_we_read_or_become_the_users_own(): void
    {
        $profile = $this->readyProfile();
        $message = $this->alertEmail('jobalerts-noreply@linkedin.com', 'Accountant: Acme Ltd and 2 more', <<<'HTML'
            <table>
            <tr><td><a href="https://www.linkedin.com/comm/jobs/view/4123456789/?trackingId=abc123xyz">Accountant</a><br>Acme Ltd · Nairobi</td></tr>
            <tr><td><a href="https://www.brightermonday.co.ke/listings/finance-officer-xyz789?utm_source=alert">Finance Officer</a><br>Beta Bank · Nairobi</td></tr>
            <tr><td><a href="https://www.linkedin.com/comm/jobs/unsubscribe?id=1">Unsubscribe</a></td></tr>
            </table>
            HTML);

        JobAlertAgent::fake([['jobs' => [
            ['title' => 'Accountant', 'company' => 'Acme Ltd', 'location' => 'Nairobi', 'link' => 1],
            ['title' => 'Finance Officer', 'company' => 'Beta Bank', 'location' => 'Nairobi', 'link' => 2],
            // A link that isn't in the email.
            ['title' => 'Auditor', 'company' => 'Gamma', 'location' => 'Nairobi', 'link' => 99],
        ]]]);

        $this->assertSame(2, app(JobAlertReader::class)->read($profile, $message));

        // The AI sees numbered links, not the tracking addresses.
        JobAlertAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Accountant [1]')
            && ! str_contains($prompt->prompt, 'trackingId'));

        $linkedIn = JobPosting::where('source', JobPosting::SOURCE_ALERT)->sole();
        $this->assertSame($this->user->id, $linkedIn->user_id);
        $this->assertSame('Acme Ltd', $linkedIn->company);
        $this->assertSame('LinkedIn', $linkedIn->sourceName());
        $this->assertNotNull($linkedIn->details_fetched_at);

        // BrighterMonday is read like its other jobs, for everyone.
        $board = JobPosting::where('source', JobPosting::SOURCE_BOARD)->sole();
        $this->assertSame('https://www.brightermonday.co.ke/listings/finance-officer-xyz789', $board->url);
        $this->assertNull($board->user_id);
        $this->assertNull($board->details_fetched_at);

        $this->assertSame('LinkedIn', $profile->fresh()->last_alert_source);
        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_JOB_ALERT_READ)->where('user_id', $this->user->id)->count());

        // The same job in the next day's alert isn't added twice.
        JobAlertAgent::fake([['jobs' => [['title' => 'Accountant', 'company' => 'Acme Ltd', 'location' => 'Nairobi', 'link' => 1]]]]);
        $this->assertSame(0, app(JobAlertReader::class)->read($profile, $message));
    }

    public function test_alerts_are_read_within_a_daily_allowance(): void
    {
        config(['job_search.alerts.max_per_user_per_day' => 1]);
        $profile = $this->readyProfile();
        JobAlertAgent::fake([['jobs' => []]]);
        $message = $this->alertEmail('noreply@fuzu.com', 'New jobs matching your profile');

        $this->assertSame(0, app(JobAlertReader::class)->read($profile, $message));
        $this->assertNull(app(JobAlertReader::class)->read($profile, $message));
    }

    // ─── Finding careers pages ───────────────────────────────────────────────

    private const SEARCH_ANSWER = "Acme Ltd | https://acme.co.ke/careers\n"
        ."LinkedIn | https://www.linkedin.com/company/acme/jobs\n"
        ."Bad Corp | https://badcorp.co.ke/jobs\n"
        ."Acme again | https://www.acme.co.ke/about\n"
        ."- Beta Bank | https://jobs.betabank.co.ke/vacancies.";

    public function test_web_search_adds_employers_careers_pages(): void
    {
        Queue::fake([SyncCareerPageJob::class]);
        CareerPageSearchAgent::fake([self::SEARCH_ANSWER]);
        $profile = $this->readyProfile();

        $this->assertSame(2, app(CareerPageDiscovery::class)->discover($profile));

        CareerPageSearchAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Accountant')
            && str_contains($prompt->prompt, 'Nairobi'));

        $pages = CompanyCareerPage::orderBy('host')->get();
        $this->assertSame(['acme.co.ke', 'jobs.betabank.co.ke'], $pages->pluck('host')->all());
        $this->assertSame('https://jobs.betabank.co.ke/vacancies', $pages[1]->url);
        $this->assertSame([CompanyCareerPage::SOURCE_SEARCH], $pages->pluck('source')->unique()->all());
        $this->assertNotNull($profile->fresh()->last_discovery_at);
        Queue::assertPushed(SyncCareerPageJob::class, 2);
    }

    public function test_search_results_are_shared_and_removed_pages_are_not_added_again(): void
    {
        Queue::fake([SyncCareerPageJob::class]);
        CareerPageSearchAgent::fake([self::SEARCH_ANSWER])->preventStrayPrompts();
        $profile = $this->readyProfile();
        app(CareerPageDiscovery::class)->discover($profile);

        // Someone else after the same roles in the same place: no new search.
        $other = User::factory()->create();
        $domain = MailgunDomain::create(['user_id' => $other->id, 'domain' => 'other.com', 'api_key' => 'key']);
        $account = MailgunAccount::create(['user_id' => $other->id, 'mailgun_domain_id' => $domain->id, 'mailbox_address' => 'me@other.com']);
        $other->update(['active_mailgun_account_id' => $account->id]);
        $otherProfile = JobSeekerProfile::create([
            ...$profile->only(['auto_apply', 'mode', 'consented_at', 'cv_path', 'parsed_at', 'profile', 'preferences', 'daily_limit', 'min_match_score']),
            'user_id' => $other->id,
        ]);

        $this->assertSame(2, app(CareerPageDiscovery::class)->discover($otherProfile));

        // The first user removes a found page; a week later it isn't re-added.
        $acme = $this->user->companyCareerPages()->where('host', 'acme.co.ke')->sole();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/job-search/career-pages/{$acme->id}")
            ->assertOk()
            ->assertJsonCount(1, 'careerPages');

        $this->assertNotNull($acme->fresh()->dismissed_at);

        $this->travel(8)->days();
        CareerPageSearchAgent::fake([self::SEARCH_ANSWER]);

        $this->assertSame(0, app(CareerPageDiscovery::class)->discover($profile->fresh()));
        $this->assertSame(1, $this->user->companyCareerPages()->active()->count());
    }

    public function test_a_found_page_that_isnt_a_careers_page_is_set_aside_without_ai(): void
    {
        Http::fake([
            'acme.co.ke/robots.txt' => Http::response('', 404),
            'acme.co.ke/about' => Http::response('<html><body><p>'.str_repeat('Acme makes the finest widgets in East Africa. ', 10).'</p></body></html>'),
        ]);
        CareerPageAgent::fake()->preventStrayPrompts();

        $page = CompanyCareerPage::create([
            'user_id' => $this->user->id,
            'url' => 'https://acme.co.ke/about',
            'url_hash' => hash('sha256', 'https://acme.co.ke/about'),
            'source' => CompanyCareerPage::SOURCE_SEARCH,
            'host' => 'acme.co.ke',
        ]);

        $this->assertSame(0, app(CompanyCareerPageSource::class)->sync($page));
        $this->assertNotNull($page->fresh()->dismissed_at);
        CareerPageAgent::assertNeverPrompted();
    }

    public function test_postings_from_removed_pages_are_not_matched(): void
    {
        $page = CompanyCareerPage::create([
            'user_id' => $this->user->id,
            'url' => 'https://acme.co.ke/careers',
            'url_hash' => hash('sha256', 'https://acme.co.ke/careers'),
            'source' => CompanyCareerPage::SOURCE_SEARCH,
            'host' => 'acme.co.ke',
            'dismissed_at' => now(),
        ]);
        $this->posting('Accountant', overrides: ['source' => JobPosting::SOURCE_COMPANY_PAGE, 'user_id' => $this->user->id, 'company_career_page_id' => $page->id]);

        $this->assertSame(0, JobPosting::availableTo($this->user->id)->count());
    }

    public function test_search_result_links_resolve_to_the_real_site(): void
    {
        Http::fake([
            'vertexaisearch.cloud.google.com/*' => Http::response('', 302, ['Location' => 'https://acme.co.ke/careers']),
            'acme.co.ke/careers' => Http::response('<html></html>'),
        ]);

        $this->assertSame(
            'https://acme.co.ke/careers',
            app(SafePageFetcher::class)->finalUrl('https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc'),
        );
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    private function settings(array $overrides = []): array
    {
        return [
            'autoApply' => true,
            'consent' => true,
            'mode' => 'review',
            'mailgunAccountId' => null,
            'preferences' => ['keywords' => ['Accountant'], 'locations' => ['Nairobi'], 'remoteOk' => false, 'excludedCompanies' => []],
            'dailyLimit' => 5,
            'minMatchScore' => 75,
            ...$overrides,
        ];
    }

    public function test_job_search_needs_consent_and_a_read_cv(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search', $this->settings(['consent' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent', 'cv']);

        $this->assertFalse((bool) JobSeekerProfile::where('user_id', $this->user->id)->value('auto_apply'));
    }

    public function test_limits_cannot_be_set_past_the_guardrails(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search', $this->settings(['dailyLimit' => 50, 'minMatchScore' => 10]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dailyLimit', 'minMatchScore']);
    }

    public function test_a_cv_is_stored_privately_and_read_once(): void
    {
        Queue::fake([ParseCvJob::class]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv', ['cv' => UploadedFile::fake()->create('My CV.pdf', 100, 'application/pdf')])
            ->assertOk()
            ->assertJsonPath('message', 'CV Uploaded')
            ->assertJsonPath('profile.cvStatus', 'reading');

        $profile = JobSeekerProfile::sole();
        Storage::disk('local')->assertExists($profile->cv_path);
        Storage::disk('public')->assertMissing($profile->cv_path);
        Queue::assertPushed(ParseCvJob::class, 1);

        CvProfileAgent::fake([['full_name' => 'Wanjiku Muhandi', 'headline' => 'Accountant', 'summary' => '', 'years_of_experience' => 6, 'skills' => ['IFRS'], 'target_titles' => ['Accountant'], 'education' => [], 'experience' => [], 'location' => 'Nairobi']]);
        (new ParseCvJob($profile->id, $profile->cv_hash))->handle();

        $this->assertSame(['Accountant'], $profile->fresh()->profile['target_titles']);
        $this->assertNotNull($profile->fresh()->parsed_at);
    }

    public function test_word_documents_are_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv', ['cv' => UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertUnprocessable();
    }

    // ─── Matching ────────────────────────────────────────────────────────────

    public function test_only_postings_passing_the_cheap_filters_reach_the_ai(): void
    {
        $profile = $this->readyProfile();
        // Sent to the AI newest first, so these are ids 1, 2 and 3.
        $strong = $this->posting('Accountant', overrides: ['posted_at' => now()->subHours(1)]);
        $manual = $this->posting('Finance Officer', 'Beta Ltd', null, ['posted_at' => now()->subHours(2)]);
        $weak = $this->posting('Senior Accountant', 'Gamma Ltd', 'hr@gamma.co.ke', ['posted_at' => now()->subHours(3)]);
        $this->posting('Chef', 'Hotel Ltd');
        $this->posting('Accountant', 'Bad Corp');
        $this->posting('Accountant', 'Mombasa Ltd', 'x@m.co.ke', ['location' => 'Mombasa']);
        $this->posting('Accountant', 'Old Ltd', 'x@o.co.ke', ['posted_at' => now()->subDays(40)]);

        JobMatchAgent::fake([['results' => [
            ['id' => '1', 'score' => 90, 'reason' => 'Strong IFRS fit.'],
            ['id' => '2', 'score' => 80, 'reason' => 'Good fit.'],
            ['id' => '3', 'score' => 40, 'reason' => 'Too senior.'],
        ]]]);
        JobApplicationAgent::fake([['subject' => 'Application for Accountant', 'body' => "Dear Hiring Manager,\n\nHello.\n\nKind regards,\nWanjiku Muhandi"]]);
        Notification::fake();

        $this->assertSame(3, app(JobMatcher::class)->matchFor($profile));

        JobMatchAgent::assertPrompted(fn (AgentPrompt $prompt) => ! str_contains($prompt->prompt, 'Title: Chef')
            && ! str_contains($prompt->prompt, 'Company: Bad Corp')
            && ! str_contains($prompt->prompt, 'Company: Mombasa Ltd')
            && ! str_contains($prompt->prompt, 'Company: Old Ltd'));

        $status = fn (JobPosting $posting) => JobApplication::where('job_posting_id', $posting->id)->value('status');

        $this->assertSame(JobApplicationStatus::PENDING_APPROVAL, $status($strong));
        $this->assertSame(JobApplicationStatus::APPLY_MANUALLY, $status($manual));
        $this->assertSame(JobApplicationStatus::NOT_MATCHED, $status($weak));
        $this->assertSame('Application for Accountant', JobApplication::where('job_posting_id', $strong->id)->value('subject'));
        Notification::assertSentTo($this->user, JobApplicationsReadyNotification::class);
        Notification::assertSentTo($this->user, JobsToApplyManuallyNotification::class, fn ($notification) => $notification
            ->toArray($this->user)['message'] === '1 Job To Apply For Yourself');

        // Already scored postings are never sent to the AI again.
        JobMatchAgent::fake()->preventStrayPrompts();
        $this->assertSame(0, app(JobMatcher::class)->matchFor($profile));
    }

    public function test_locations_match_whole_place_names_whatever_the_spelling(): void
    {
        $profile = $this->readyProfile([
            'preferences' => ['keywords' => [], 'locations' => ["Murang'a", 'Iten'], 'remote_ok' => false, 'excluded_companies' => []],
        ]);
        $this->posting('Accountant', 'Muranga Ltd', 'x@mu.co.ke', ['location' => 'Muranga, Kenya']);
        $this->posting('Accountant', 'Iten Ltd', 'x@it.co.ke', ['location' => 'Iten, Elgeyo-Marakwet']);
        $this->posting('Accountant', 'Kitengela Ltd', 'x@ki.co.ke', ['location' => 'Kitengela']);

        JobMatchAgent::fake([['results' => [
            ['id' => '1', 'score' => 40, 'reason' => 'Weak fit.'],
            ['id' => '2', 'score' => 40, 'reason' => 'Weak fit.'],
        ]]]);

        $this->assertSame(2, app(JobMatcher::class)->matchFor($profile));

        JobMatchAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Company: Muranga Ltd')
            && str_contains($prompt->prompt, 'Company: Iten Ltd')
            && ! str_contains($prompt->prompt, 'Company: Kitengela Ltd'));
    }

    public function test_a_job_on_several_boards_is_matched_once_and_closed_jobs_not_at_all(): void
    {
        $profile = $this->readyProfile();
        $this->posting('Accountant', 'Acme Ltd', null, ['posted_at' => now()->subHours(1)]);
        $withEmail = $this->posting('ACCOUNTANT', 'ACME Limited', 'jobs@acme.co.ke', ['posted_at' => now()->subHours(2)]);
        $this->posting('Finance Officer', 'Beta Ltd', 'hr@beta.co.ke', ['valid_through' => now()->subDay()]);

        // Of the two copies, the one that can be applied to by email.
        $this->assertSame([$withEmail->id], app(JobMatcher::class)->candidates($profile)->pluck('id')->all());

        // Once seen through one board, the copy on another isn't considered.
        JobApplication::create(['user_id' => $this->user->id, 'job_posting_id' => $withEmail->id, 'status' => JobApplicationStatus::NOT_MATCHED, 'match_score' => 20]);

        $this->assertCount(0, app(JobMatcher::class)->candidates($profile));
    }

    public function test_scoring_stops_at_the_daily_allowance(): void
    {
        config(['job_search.max_scored_per_day' => 2]);
        $profile = $this->readyProfile();

        foreach (['Accountant', 'Finance Officer', 'Accountant II'] as $index => $title) {
            $this->posting($title, "Company {$index}");
        }

        JobMatchAgent::fake([['results' => [['id' => '1', 'score' => 10, 'reason' => 'No.'], ['id' => '2', 'score' => 10, 'reason' => 'No.']]]]);

        $this->assertSame(2, app(JobMatcher::class)->matchFor($profile));
        $this->assertSame(0, app(JobMatcher::class)->matchFor($profile));
    }

    public function test_no_ai_is_used_while_the_review_backlog_is_full(): void
    {
        config(['job_search.max_pending_drafts' => 1]);
        $profile = $this->readyProfile();
        $this->queuedApplication($this->posting('Accountant'), ['status' => JobApplicationStatus::PENDING_APPROVAL]);
        $this->posting('Finance Officer', 'Beta Ltd');

        JobMatchAgent::fake()->preventStrayPrompts();

        $this->assertSame(0, app(JobMatcher::class)->matchFor($profile));
        JobMatchAgent::assertNeverPrompted();
    }

    // ─── Sending ─────────────────────────────────────────────────────────────

    public function test_an_approved_application_is_sent_from_the_users_mailbox_with_the_cv(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        Notification::fake();
        $this->duringWorkingHours();
        $profile = $this->readyProfile();
        $application = $this->queuedApplication($this->posting('Accountant'));

        $this->assertTrue(app(JobApplicationSender::class)->sendNext($profile));

        $application->refresh();
        $message = MailMessage::with('attachments')->findOrFail($application->mail_message_id);

        $this->assertSame(JobApplicationStatus::APPLIED, $application->status);
        $this->assertSame('wanjiku@example.com', $message->from_address['address']);
        $this->assertSame('jobs@acme.co.ke', $message->to[0]['address']);
        $this->assertSame('Application for Accountant', $message->subject);
        $this->assertSame('local', $message->attachments->sole()->disk);
        $this->assertSame('Wanjiku CV.pdf', $message->attachments->sole()->original_name);
        Storage::disk('local')->assertExists($message->attachments->sole()->path);
        Queue::assertPushed(SendMailMessageJob::class, fn ($job) => $job->mailMessageId === $message->id);
        Notification::assertSentTo($this->user, JobApplicationSentNotification::class, fn ($notification, array $channels) => $channels === ['database', 'broadcast', WebPushChannel::class, 'mail']);
    }

    public function test_the_user_is_emailed_about_every_application_sent_for_them(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        $this->duringWorkingHours();
        $profile = $this->readyProfile(['mode' => 'autopilot']);
        $application = $this->queuedApplication($this->posting('Accountant'));

        app(JobApplicationSender::class)->sendNext($profile);

        $notification = new JobApplicationSentNotification($application->fresh('posting'));
        $mail = $notification->toMail($this->user);
        $push = $notification->toWebPush($this->user, $notification)->toArray();

        $this->assertSame('Applied To Accountant At Acme Ltd', $mail->subject);
        $this->assertStringContainsString('jobs@acme.co.ke', (string) $mail->render());
        $this->assertSame('Applied To Accountant At Acme Ltd', $push['title']);
        $this->assertSame('/mail/'.$application->fresh()->mail_thread_id.'/show', $push['data']['url']);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
            'type' => JobApplicationSentNotification::class,
        ]);
    }

    public function test_sending_respects_the_daily_limit_and_working_hours(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        $profile = $this->readyProfile(['daily_limit' => 1]);
        $this->queuedApplication($this->posting('Accountant'));
        $this->queuedApplication($this->posting('Finance Officer', 'Beta Ltd', 'hr@beta.co.ke'));

        $this->travelTo(Carbon::parse('2026-11-07 10:00:00', 'Africa/Nairobi')); // Saturday
        $this->assertFalse(app(JobApplicationSender::class)->sendNext($profile));

        $this->travelTo(Carbon::parse('2026-11-04 19:00:00', 'Africa/Nairobi'));
        $this->assertFalse(app(JobApplicationSender::class)->sendNext($profile));

        $this->duringWorkingHours();
        $this->assertTrue(app(JobApplicationSender::class)->sendNext($profile));
        $this->assertFalse(app(JobApplicationSender::class)->sendNext($profile));
        $this->assertSame(1, JobApplication::where('status', JobApplicationStatus::QUEUED)->count());
    }

    public function test_a_long_cover_letter_is_sent_with_a_snippet_that_fits(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        $this->duringWorkingHours();
        $profile = $this->readyProfile();
        $application = $this->queuedApplication($this->posting('Accountant'), [
            'cover_letter' => str_repeat('I would like to apply for this role. ', 30),
        ]);

        $this->assertTrue(app(JobApplicationSender::class)->sendNext($profile));
        $this->assertSame(JobApplicationStatus::APPLIED, $application->fresh()->status);
        $this->assertLessThanOrEqual(
            MailMessage::SNIPPET_LENGTH,
            mb_strlen(MailMessage::query()
                ->findOrFail($application->fresh()->mail_message_id)
                ->snippet),
        );
    }

    public function test_the_same_company_is_not_applied_to_twice_within_the_cooldown(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        $this->duringWorkingHours();
        $profile = $this->readyProfile();
        $this->queuedApplication($this->posting('Accountant', 'Acme Ltd'), [
            'status' => JobApplicationStatus::APPLIED,
            'applied_at' => now()->subDays(5),
        ]);
        $second = $this->queuedApplication($this->posting('Finance Officer', 'ACME Limited'));

        $this->assertFalse(app(JobApplicationSender::class)->sendNext($profile));
        $this->assertSame(JobApplicationStatus::SKIPPED, $second->fresh()->status);
    }

    public function test_switching_off_holds_back_anything_not_yet_sent(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        $this->duringWorkingHours();
        $profile = $this->readyProfile();
        $application = $this->queuedApplication($this->posting('Accountant'));

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search', $this->settings(['autoApply' => false]))
            ->assertOk()
            ->assertJsonPath('profile.autoApply', false);

        $this->assertSame(JobApplicationStatus::PENDING_APPROVAL, $application->fresh()->status);
        $this->assertFalse(app(JobApplicationSender::class)->sendNext($profile->fresh()));
        Queue::assertNothingPushed();
    }

    public function test_repeated_bounces_pause_job_search(): void
    {
        Notification::fake();
        $profile = $this->readyProfile();

        foreach (['Accountant', 'Finance Officer', 'Auditor'] as $index => $title) {
            $application = $this->queuedApplication($this->posting($title, "Company {$index}"), ['status' => JobApplicationStatus::APPLIED, 'applied_at' => now()]);
            $message = $this->sentMessage();
            $application->update(['mail_message_id' => $message->id]);

            event(new MailMessageStatusUpdatedEvent($this->user->id, $message->id, $message->mail_thread_id, 'bounced', 'No such mailbox'));
        }

        $this->assertFalse($profile->fresh()->auto_apply);
        $this->assertSame(3, JobApplication::where('status', JobApplicationStatus::FAILED)->count());
        Notification::assertSentTo($this->user, JobSearchPausedNotification::class, fn ($notification, array $channels) => in_array('mail', $channels, true));
        Notification::assertSentToTimes($this->user, JobApplicationFailedNotification::class, 3);
    }

    public function test_an_employer_reply_marks_the_application_replied(): void
    {
        Http::fake();
        Notification::fake();
        config(['services.mailgun.webhook_signing_secret' => 'secret']);
        $application = $this->queuedApplication($this->posting('Accountant'), ['status' => JobApplicationStatus::APPLIED, 'applied_at' => now()]);
        $sent = $this->sentMessage(['message_id' => 'application-1@example.com']);
        $application->update(['mail_message_id' => $sent->id, 'mail_thread_id' => $sent->mail_thread_id]);

        $timestamp = (string) now()->timestamp;
        $this->postJson('/api/webhooks/mailgun', [
            'timestamp' => $timestamp,
            'token' => 'reply-token',
            'signature' => hash_hmac('sha256', $timestamp.'reply-token', 'secret'),
            'recipient' => 'wanjiku@example.com',
            'sender' => 'jobs@acme.co.ke',
            'from' => 'Acme HR <jobs@acme.co.ke>',
            'To' => 'wanjiku@example.com',
            'subject' => 'Re: Application for Accountant',
            'body-plain' => 'Please come for an interview.',
            'Message-Id' => '<reply-1@acme.co.ke>',
            'In-Reply-To' => '<application-1@example.com>',
        ])->assertOk();

        $this->assertSame(JobApplicationStatus::REPLIED, $application->fresh()->status);
        Notification::assertSentToTimes($this->user, JobApplicationRepliedNotification::class, 1);

        // A second reply in the same thread doesn't notify again.
        $this->postJson('/api/webhooks/mailgun', [
            'timestamp' => $timestamp,
            'token' => 'reply-token-2',
            'signature' => hash_hmac('sha256', $timestamp.'reply-token-2', 'secret'),
            'recipient' => 'wanjiku@example.com',
            'sender' => 'jobs@acme.co.ke',
            'from' => 'Acme HR <jobs@acme.co.ke>',
            'To' => 'wanjiku@example.com',
            'subject' => 'Re: Application for Accountant',
            'body-plain' => 'Any day this week works.',
            'Message-Id' => '<reply-2@acme.co.ke>',
            'In-Reply-To' => '<reply-1@acme.co.ke>',
        ])->assertOk();

        Notification::assertSentToTimes($this->user, JobApplicationRepliedNotification::class, 1);
    }

    private function sentMessage(array $overrides = []): MailMessage
    {
        $thread = \App\Models\MailThread::create(['user_id' => $this->user->id, 'subject' => 'Application for Accountant']);

        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => ['address' => 'wanjiku@example.com'],
            'to' => [['address' => 'jobs@acme.co.ke']],
            'subject' => 'Application for Accountant',
            'status' => 'sent',
            ...$overrides,
        ]);
    }

    // ─── Cover letters ───────────────────────────────────────────────────────

    private function draftFor(JobSeekerProfile $profile): JobApplication
    {
        $application = $this->queuedApplication($this->posting('Accountant'), [
            'status' => JobApplicationStatus::PENDING_APPROVAL,
            'subject' => null,
            'cover_letter' => null,
        ]);

        app(JobMatcher::class)->draft($profile, $application);

        return $application->refresh();
    }

    public function test_a_cover_letter_sent_as_written_uses_no_ai(): void
    {
        JobApplicationAgent::fake()->preventStrayPrompts();
        $profile = $this->readyProfile([
            'cover_letter' => "Dear Hiring Manager,\n\nI'd like to join { Company } as your {role}.\n\nKind regards,\nWanjiku",
            'cover_letter_mode' => 'as_is',
        ]);

        $application = $this->draftFor($profile);

        $this->assertSame('Application for Accountant', $application->subject);
        $this->assertStringContainsString("I'd like to join Acme Ltd as your Accountant.", $application->cover_letter);
        JobApplicationAgent::assertNeverPrompted();
    }

    public function test_the_ai_tailors_the_users_own_cover_letter(): void
    {
        JobApplicationAgent::fake([['subject' => 'Application for Accountant', 'body' => 'Tailored letter.']]);
        $profile = $this->readyProfile(['cover_letter' => 'My own letter about audit work.']);

        $this->assertSame('Tailored letter.', $this->draftFor($profile)->cover_letter);

        JobApplicationAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, "The candidate's own cover letter:\nMy own letter about audit work."));
    }

    public function test_a_cover_letter_can_be_uploaded_or_typed(): void
    {
        $this->readyProfile();

        // A Word document: only its text is kept.
        $path = tempnam(sys_get_temp_dir(), 'letter');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Dear Hiring Manager,</w:t></w:r></w:p><w:p><w:r><w:t>I&apos;m an accountant at {company}.</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/job-search/cover-letter', ['coverLetter' => new UploadedFile($path, 'Cover letter.docx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Cover Letter Uploaded')
            ->assertJsonPath('profile.coverLetter', "Dear Hiring Manager,\nI'm an accountant at {company}.");

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search/cover-letter', ['coverLetter' => '', 'coverLetterMode' => 'as_is'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coverLetter');

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search/cover-letter', ['coverLetter' => ' Dear {company}, ', 'coverLetterMode' => 'as_is'])
            ->assertOk()
            ->assertJsonPath('message', 'Cover Letter Saved')
            ->assertJsonPath('profile.coverLetter', 'Dear {company},')
            ->assertJsonPath('profile.coverLetterMode', 'as_is');
    }

    // ─── Review page ─────────────────────────────────────────────────────────

    public function test_drafts_can_be_edited_approved_or_skipped(): void
    {
        Queue::fake();
        $this->readyProfile();
        $draft = $this->queuedApplication($this->posting('Accountant'), ['status' => JobApplicationStatus::PENDING_APPROVAL]);
        $other = $this->queuedApplication($this->posting('Auditor', 'Beta Ltd'), ['status' => JobApplicationStatus::PENDING_APPROVAL]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=review')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('counts.review', 2);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$draft->id}/approve", ['subject' => 'Accountant – ACC-01'])
            ->assertOk()
            ->assertJsonPath('message', 'Application Approved')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.subject', 'Accountant – ACC-01');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$other->id}/skip")
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/job-applications/{$draft->id}/skip")
            ->assertNotFound();
    }

    public function test_deleting_job_search_data_removes_the_cv(): void
    {
        $profile = $this->readyProfile();
        $this->queuedApplication($this->posting('Accountant'));

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/job-search')
            ->assertOk()
            ->assertJsonPath('profile', null);

        Storage::disk('local')->assertMissing($profile->cv_path);
        $this->assertSame(0, JobApplication::count());
        $this->assertSame(0, JobSeekerProfile::count());
    }
}
