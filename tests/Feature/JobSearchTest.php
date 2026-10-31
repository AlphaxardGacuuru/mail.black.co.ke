<?php

namespace Tests\Feature;

use App\Agents\CareerPageAgent;
use App\Agents\CareerPageSearchAgent;
use App\Agents\CvProfileAgent;
use App\Agents\JobApplicationAgent;
use App\Agents\JobMatchAgent;
use App\Enums\JobApplicationStatus;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Http\Services\Jobs\CareerPageDiscovery;
use App\Http\Services\Jobs\CompanyCareerPageSource;
use App\Http\Services\Jobs\JobApplicationSender;
use App\Http\Services\Jobs\JobMatcher;
use App\Http\Services\Jobs\MyJobMagSource;
use App\Http\Services\Jobs\SafePageFetcher;
use App\Jobs\ParseCvJob;
use App\Jobs\SendMailMessageJob;
use App\Jobs\SyncCareerPageJob;
use App\Models\CompanyCareerPage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\User;
use App\Notifications\JobApplicationsReadyNotification;
use App\Notifications\JobApplicationSentNotification;
use App\Notifications\JobSearchPausedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

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
            'source' => JobPosting::SOURCE_MYJOBMAG,
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

        $source = app(MyJobMagSource::class);

        $this->assertSame(1, $source->syncFeed());

        $posting = JobPosting::sole();
        $this->assertSame('Accountant', $posting->title);
        $this->assertSame('Acme Ltd', $posting->company);

        $source->fetchDetails($posting);
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

        // Already scored postings are never sent to the AI again.
        JobMatchAgent::fake()->preventStrayPrompts();
        $this->assertSame(0, app(JobMatcher::class)->matchFor($profile));
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
        Notification::assertSentTo($this->user, JobApplicationSentNotification::class);
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
        Notification::assertSentTo($this->user, JobSearchPausedNotification::class);
    }

    public function test_an_employer_reply_marks_the_application_replied(): void
    {
        Http::fake();
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
