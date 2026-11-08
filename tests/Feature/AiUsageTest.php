<?php

namespace Tests\Feature;

use App\Agents\CareerPageAgent;
use App\Agents\CareerPageSearchAgent;
use App\Agents\CvProfileAgent;
use App\Agents\JobApplicationAgent;
use App\Agents\JobMatchAgent;
use App\Agents\ReplySuggestionAgent;
use App\Jobs\DraftJobApplicationJob;
use App\Jobs\ParseCvJob;
use App\Jobs\SyncCareerPageJob;
use App\Models\AiUsage;
use App\Models\CompanyCareerPage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Services\AiUsageService;
use App\Services\Jobs\CareerPageDiscovery;
use App\Services\Jobs\CompanyCareerPageSource;
use App\Services\Jobs\JobMatcher;
use App\Services\Jobs\SafePageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use RuntimeException;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'alphaxardgacuuru47@gmail.com';

    private const MODEL = 'gemini-2.5-flash-lite';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config()->set('ai.pricing', [self::MODEL => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.40]]);

        // Every host resolves to a public address without real DNS.
        $this->app->instance(SafePageFetcher::class, new class extends SafePageFetcher
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    // ─── Recording ───────────────────────────────────────────────────────────

    public function test_a_reply_suggestion_records_its_tokens_and_cost(): void
    {
        $user = User::factory()
            ->create();
        $message = $this->message($user);

        ReplySuggestionAgent::fake([new TextResponse('Hi Kevin', new Usage(promptTokens: 2000, completionTokens: 300, cacheReadInputTokens: 400, reasoningTokens: 100), new Meta('gemini', 'models/'.self::MODEL))]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk();

        $usage = AiUsage::query()
            ->sole();

        $this->assertSame($user->id, $usage->user_id);
        $this->assertTrue($usage->subject->is($message));
        $this->assertSame(AiUsage::FEATURE_REPLY_SUGGESTION, $usage->feature);
        $this->assertSame('gemini', $usage->provider);
        $this->assertSame(self::MODEL, $usage->model);
        $this->assertNull($usage->attempt);
        $this->assertSame(2000, $usage->input_tokens);
        $this->assertSame(400, $usage->cached_input_tokens);
        $this->assertSame(300, $usage->output_tokens);
        $this->assertSame(100, $usage->reasoning_tokens);
        // 2000 x 0.10 + 400 x 0.01 + (300 + 100) x 0.40 = 200 + 4 + 160 micro-dollars.
        $this->assertSame(364, $usage->cost_micros);
    }

    public function test_a_cached_reply_suggestion_records_no_new_usage(): void
    {
        $user = User::factory()
            ->create();
        $message = $this->message($user);

        ReplySuggestionAgent::fake(['First draft', 'Second draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertOk()
            ->assertJsonPath('cached', true);

        $this->assertSame(1, AiUsage::query()
            ->count());
    }

    public function test_a_failed_ai_request_records_no_usage(): void
    {
        $user = User::factory()
            ->create();
        $message = $this->message($user);

        ReplySuggestionAgent::fake(fn () => throw new RuntimeException('Provider unavailable'));

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/suggest-reply")
            ->assertStatus(503);

        $this->assertSame(0, AiUsage::query()
            ->count());
    }

    public function test_reading_a_cv_records_its_usage(): void
    {
        $profile = $this->readyProfile();

        CvProfileAgent::fake([$this->structured(['full_name' => 'Wanjiku Muhandi', 'target_titles' => ['Accountant']], new Usage(3000, 200))]);

        (new ParseCvJob($profile->id, $profile->cv_hash))
            ->handle();

        $usage = AiUsage::query()
            ->sole();

        $this->assertSame($profile->user_id, $usage->user_id);
        $this->assertSame(AiUsage::FEATURE_CV_PARSE, $usage->feature);
        $this->assertTrue($usage->subject->is($profile));
        $this->assertSame(380, $usage->cost_micros);
    }

    public function test_matching_and_drafting_record_usage_for_the_job_seeker(): void
    {
        Notification::fake();
        Queue::fake([DraftJobApplicationJob::class]);

        $profile = $this->readyProfile();
        $posting = $this->posting('Accountant');

        JobMatchAgent::fake([$this->structured(['results' => [['id' => '1', 'score' => 90, 'reason' => 'Strong fit.']]], new Usage(1000, 100))]);
        JobApplicationAgent::fake([$this->structured(['subject' => 'Application for Accountant', 'body' => 'Dear Hiring Manager'], new Usage(800, 300))]);

        app(JobMatcher::class)
            ->matchFor($profile);

        $application = JobApplication::query()
            ->sole();
        app(JobMatcher::class)
            ->draft($profile, $application);

        $match = AiUsage::query()
            ->where('feature', AiUsage::FEATURE_JOB_MATCH)
            ->sole();
        $draft = AiUsage::query()
            ->where('feature', AiUsage::FEATURE_JOB_APPLICATION_DRAFT)
            ->sole();

        $this->assertSame($profile->user_id, $match->user_id);
        $this->assertTrue($match->subject->is($profile));
        $this->assertSame(140, $match->cost_micros);
        $this->assertSame($profile->user_id, $draft->user_id);
        $this->assertTrue($draft->subject->is($application));
        $this->assertSame($posting->id, $application->job_posting_id);
    }

    public function test_reading_a_careers_page_records_usage_for_its_owner(): void
    {
        $user = User::factory()
            ->create();

        Http::fake([
            'acme.co.ke/robots.txt' => Http::response('', 404),
            'acme.co.ke/careers' => Http::response('<html><body><h2>Finance Officer</h2></body></html>'),
        ]);
        CareerPageAgent::fake([$this->structured(['company' => 'Acme', 'jobs' => []], new Usage(5000, 50))]);

        $page = CompanyCareerPage::create(['user_id' => $user->id, 'url' => 'https://acme.co.ke/careers', 'url_hash' => hash('sha256', 'https://acme.co.ke/careers')]);

        app(CompanyCareerPageSource::class)
            ->sync($page);

        $usage = AiUsage::query()
            ->sole();

        $this->assertSame($user->id, $usage->user_id);
        $this->assertSame(AiUsage::FEATURE_CAREER_PAGE_READ, $usage->feature);
        $this->assertTrue($usage->subject->is($page));
    }

    public function test_a_shared_careers_page_search_is_recorded_as_system_usage(): void
    {
        Queue::fake([SyncCareerPageJob::class]);
        CareerPageSearchAgent::fake([new TextResponse('Acme Ltd | https://acme.co.ke/careers', new Usage(600, 80), new Meta('gemini', self::MODEL))]);

        app(CareerPageDiscovery::class)
            ->discover($this->readyProfile());

        $usage = AiUsage::query()
            ->sole();

        $this->assertNull($usage->user_id);
        $this->assertNull($usage->subject_id);
        $this->assertSame(AiUsage::FEATURE_CAREER_PAGE_SEARCH, $usage->feature);
        // Tokens (60 + 32) plus one Google search at $14 per 1,000.
        $this->assertSame(1, $usage->searches);
        $this->assertSame(14092, $usage->cost_micros);
    }

    public function test_a_paid_service_is_recorded_with_its_price(): void
    {
        $user = User::factory()
            ->create();

        $usage = app(AiUsageService::class)
            ->recordService($user->id, AiUsage::FEATURE_CAPTCHA_SOLVE, '2captcha', 'recaptcha_v2', 3000);

        $this->assertSame(0, $usage->input_tokens);
        $this->assertSame(3000, $usage->cost_micros);
        $this->assertSame('recaptcha_v2', $usage->model);
    }

    public function test_usage_is_kept_when_the_message_is_deleted(): void
    {
        $user = User::factory()
            ->create();
        $message = $this->message($user);
        AiUsage::factory()
            ->for($user)
            ->create(['subject_type' => $message->getMorphClass(), 'subject_id' => $message->id]);

        $message->delete();

        $this->assertSame(1, AiUsage::query()
            ->count(), 'Deleting a message must not erase what it cost.');
    }

    public function test_models_without_a_price_are_recorded_at_zero_cost(): void
    {
        $service = app(AiUsageService::class);

        $this->assertSame(0, $service->costInMicros('some-unknown-model', 1000, 0, 1000));
        $this->assertSame(0, $service->costInMicros(null, 1000, 0, 1000));
    }

    // ─── Admin page ──────────────────────────────────────────────────────────

    public function test_admins_see_this_months_usage_totalled_per_user(): void
    {
        $admin = User::factory()
            ->create(['email' => self::ADMIN_EMAIL]);
        $heavy = User::factory()
            ->create(['name' => 'Heavy User']);
        $light = User::factory()
            ->create(['name' => 'Light User']);

        $this->travelTo('2026-10-15 12:00');

        AiUsage::factory()
            ->for($heavy)
            ->count(2)
            ->create(['input_tokens' => 1000, 'cached_input_tokens' => 0, 'output_tokens' => 100, 'reasoning_tokens' => 0, 'cost_micros' => 400]);
        AiUsage::factory()
            ->for($heavy)
            ->jobMatch()
            ->create(['input_tokens' => 2000, 'cached_input_tokens' => 0, 'output_tokens' => 50, 'reasoning_tokens' => 0, 'cost_micros' => 600]);
        AiUsage::factory()
            ->system()
            ->create(['input_tokens' => 300, 'cached_input_tokens' => 0, 'output_tokens' => 20, 'reasoning_tokens' => 0, 'cost_micros' => 200]);
        AiUsage::factory()
            ->for($light)
            ->create(['input_tokens' => 500, 'cached_input_tokens' => 0, 'output_tokens' => 10, 'reasoning_tokens' => 0, 'cost_micros' => 100]);
        AiUsage::factory()
            ->for($light)
            ->create(['cost_micros' => 9999, 'created_at' => '2026-09-30 23:00']);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonPath('totals.costMicros', 1700)
            ->assertJsonPath('totals.requests', 5)
            ->assertJsonPath('totals.replySuggestions', 3)
            ->assertJsonPath('users.0.name', 'Heavy User')
            ->assertJsonPath('users.0.requests', 3)
            ->assertJsonPath('users.0.replySuggestions', 2)
            ->assertJsonPath('users.0.inputTokens', 4000)
            ->assertJsonPath('users.0.outputTokens', 250)
            ->assertJsonPath('users.0.costMicros', 1400)
            ->assertJsonPath('users.1.userId', null)
            ->assertJsonPath('users.1.name', 'System')
            ->assertJsonPath('users.1.email', null)
            ->assertJsonPath('users.1.replySuggestions', 0)
            ->assertJsonPath('users.1.costMicros', 200)
            ->assertJsonPath('users.2.name', 'Light User')
            ->assertJsonPath('users.2.costMicros', 100);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('totals.costMicros', 9999);
    }

    public function test_admins_see_what_each_feature_costs_per_use(): void
    {
        $admin = User::factory()
            ->create(['email' => self::ADMIN_EMAIL]);
        $user = User::factory()
            ->create(['name' => 'Job Seeker']);
        $other = User::factory()
            ->create();
        // What each request was about: a CV, an application.
        $about = fn (string $type) => ['subject_type' => $type, 'subject_id' => (string) str()->uuid()];
        $cv = fn (User $owner) => ['subject_type' => 'cv_revamp', 'subject_id' => $owner->id];

        $this->travelTo('2026-10-15 12:00');

        // One CV revamped twice (with answers) and another once: two
        // revamps, three requests.
        $revamp = fn (User $owner, int $cost) => AiUsage::factory()
            ->for($owner)
            ->create(['feature' => AiUsage::FEATURE_CV_REVAMP, ...$cv($owner), 'cost_micros' => $cost]);
        $revamp($user, 3000);
        $revamp($user, 3000);
        $revamp($other, 6000);
        AiUsage::factory()
            ->for($user)
            ->create(['feature' => AiUsage::FEATURE_CAPTCHA_SOLVE, ...$about('job_application'), 'cost_micros' => 3000]);
        AiUsage::factory()
            ->for($other)
            ->create(['feature' => AiUsage::FEATURE_CAPTCHA_SOLVE, ...$about('job_application'), 'cost_micros' => 3000]);
        AiUsage::factory()
            ->system()
            ->create(['searches' => 1, 'cost_micros' => 14100]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertJsonPath('features.0.feature', AiUsage::FEATURE_CAREER_PAGE_SEARCH)
            ->assertJsonPath('features.0.searches', 1)
            ->assertJsonPath('features.1.feature', AiUsage::FEATURE_CV_REVAMP)
            ->assertJsonPath('features.1.requests', 3)
            ->assertJsonPath('features.1.users', 2)
            ->assertJsonPath('features.1.items', 2)
            ->assertJsonPath('features.1.costMicros', 12000)
            ->assertJsonPath('features.1.costPerRequestMicros', 4000)
            ->assertJsonPath('features.1.costPerItemMicros', 6000)
            ->assertJsonPath('features.2.feature', AiUsage::FEATURE_CAPTCHA_SOLVE)
            ->assertJsonPath('features.2.costPerItemMicros', 3000);

        $seeker = collect($response->json('users'))
            ->firstWhere('name', 'Job Seeker');

        $this->assertSame(
            [AiUsage::FEATURE_CV_REVAMP => 6000, AiUsage::FEATURE_CAPTCHA_SOLVE => 3000],
            collect($seeker['features'])
                ->pluck('costMicros', 'feature')
                ->all(),
        );
    }

    public function test_the_month_must_be_valid(): void
    {
        $admin = User::factory()
            ->create(['email' => self::ADMIN_EMAIL]);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index', ['month' => 'October']))
            ->assertJsonValidationErrors('month');
    }

    public function test_only_admins_can_see_ai_usage(): void
    {
        $user = User::factory()
            ->create();

        $this->getJson(route('admin.ai-usage.index'))
            ->assertUnauthorized();
        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.ai-usage.index'))
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth')
            ->assertJsonPath('data.can_view_ai_usage', false);
        $admin = User::factory()
            ->create(['email' => self::ADMIN_EMAIL]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth')
            ->assertJsonPath('data.can_view_ai_usage', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function message(User $user): MailMessage
    {
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Viewing on Saturday']);

        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'inbound',
            'folder' => 'inbox',
            'from_address' => ['name' => 'Kevin Otieno', 'address' => 'kevin@example.com'],
            'to' => [['name' => $user->name, 'address' => $user->email]],
            'subject' => $thread->subject,
            'body_text' => 'Can I view it on Saturday?',
        ]);
    }

    private function readyProfile(): JobSeekerProfile
    {
        $user = User::factory()
            ->create(['name' => 'Wanjiku Muhandi']);
        $domain = MailgunDomain::create(['user_id' => $user->id, 'domain' => 'example.com', 'api_key' => 'key']);
        $account = MailgunAccount::create(['user_id' => $user->id, 'mailgun_domain_id' => $domain->id, 'mailbox_address' => 'wanjiku@example.com']);
        $user->update(['active_mailgun_account_id' => $account->id]);

        Storage::disk('local')
            ->put('job-search/cv/wanjiku.pdf', '%PDF-1.4 fake');

        return JobSeekerProfile::create([
            'user_id' => $user->id,
            'auto_apply' => true,
            'mode' => 'review',
            'consented_at' => now(),
            'cv_path' => 'job-search/cv/wanjiku.pdf',
            'cv_original_name' => 'Wanjiku CV.pdf',
            'cv_hash' => str_repeat('a', 64),
            'parsed_at' => now(),
            'profile' => [
                'full_name' => 'Wanjiku Muhandi',
                'headline' => 'Accountant',
                'summary' => 'CPA(K) accountant.',
                'years_of_experience' => 6,
                'skills' => ['IFRS'],
                'target_titles' => ['Accountant'],
                'education' => [],
                'experience' => [],
                'location' => 'Nairobi',
            ],
            'preferences' => ['keywords' => [], 'locations' => ['Nairobi'], 'remote_ok' => false, 'excluded_companies' => []],
            'daily_limit' => 5,
            'min_match_score' => 75,
        ]);
    }

    private function posting(string $title): JobPosting
    {
        return JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', $title, 'Acme Ltd'),
            'title' => $title,
            'company' => 'Acme Ltd',
            'location' => 'Nairobi',
            'description' => "We need a {$title} with IFRS experience.",
            'url' => 'https://www.myjobmag.co.ke/jobs/'.str($title)->slug(),
            'apply_email' => 'jobs@acme.co.ke',
            'posted_at' => now()->subDay(),
            'details_fetched_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function structured(array $structured, Usage $usage): StructuredTextResponse
    {
        return new StructuredTextResponse($structured, json_encode($structured), $usage, new Meta('gemini', self::MODEL));
    }
}
