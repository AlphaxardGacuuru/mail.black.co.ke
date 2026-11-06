<?php

namespace Tests\Feature;

use App\Agents\CareerFieldsAgent;
use App\Agents\CareerNewsAgent;
use App\Jobs\PostCareerNewsStoriesJob;
use App\Models\AiUsage;
use App\Models\JobSeekerProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\Jobs\SafePageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class CareerNewsStoryTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWER = <<<'TEXT'
    KRA changes VAT rules for SMEs | Business Daily | https://news.example.com/kra-vat | Accountants filing for small businesses need to update returns.
    Big Four firm hires 200 auditors | The Star | https://news.example.com/audit-hiring | Audit firms in Nairobi are hiring this quarter.
    A made-up story | Nowhere | https://news.example.com/missing | Should be dropped because the page doesn't load.
    Not an article line
    TEXT;

    private User $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->account = User::factory()->create([
            'name' => 'Black Mail',
            'email' => 'al@mail.black.co.ke',
            'avatar' => 'https://mail.black.co.ke/android-chrome-512x512.png',
        ]);

        // Every host resolves to a public address without real DNS.
        $this->app->instance(SafePageFetcher::class, new class extends SafePageFetcher
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            '*/missing' => Http::response('', 404),
            '*' => Http::response('<html><body>Article</body></html>'),
        ]);

        // Accountants and auditors are both finance; engineers are IT.
        CareerFieldsAgent::fake(fn (string $prompt) => [
            'fields' => str_contains($prompt, 'Engineer') ? ['software_it'] : ['accounting_finance'],
        ]);
    }

    /**
     * @param  array<int, string>  $titles
     */
    private function profile(array $titles, string $name = 'Wanjiku Muhandi'): JobSeekerProfile
    {
        return JobSeekerProfile::create([
            'user_id' => User::factory()->create()->id,
            'auto_apply' => true,
            'mode' => 'review',
            'consented_at' => now(),
            'parsed_at' => now(),
            'profile' => [
                'full_name' => $name,
                'headline' => 'Six years in '.$titles[0],
                'skills' => ['IFRS', 'Audit'],
                'target_titles' => $titles,
                'location' => 'Nairobi',
            ],
            'preferences' => [],
            'daily_limit' => 5,
            'min_match_score' => 75,
        ]);
    }

    public function test_similar_roles_share_one_story_for_their_field(): void
    {
        CareerNewsAgent::fake([self::ANSWER]);
        $accountant = $this->profile(['Accountant', 'Finance Officer']);
        $auditor = $this->profile(['Auditor', 'Accountant'], 'Otieno Barasa');

        PostCareerNewsStoriesJob::dispatchSync();

        $this->assertSame(['accounting_finance'], $accountant->fresh()->career_fields);
        $this->assertSame(['accounting_finance'], $auditor->fresh()->career_fields);

        CareerNewsAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Field: Accounting & finance')
            && str_contains($prompt->prompt, 'Roles: Accountant, Finance Officer, Auditor')
            && ! str_contains($prompt->prompt, 'Wanjiku')
            && ! str_contains($prompt->prompt, 'Nairobi'));

        $story = Story::sole();

        $this->assertSame($this->account->id, $story->user_id);
        $this->assertSame('accounting_finance', $story->career_field);
        $this->assertSame('Accounting & finance', $story->text);
        $this->assertCount(2, $story->media);
        $this->assertSame('https://news.example.com/kra-vat', $story->media[0]['url']);
        $this->assertSame('Business Daily', $story->media[0]['source']);
        Storage::disk('public')->assertExists($story->media[0]['image']);
        $this->assertSame(2, AiUsage::where('feature', AiUsage::FEATURE_CAREER_FIELDS)->count());
        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_CAREER_NEWS)->count());
    }

    public function test_a_cv_is_only_tagged_again_once_it_changes(): void
    {
        CareerNewsAgent::fake([self::ANSWER, self::ANSWER, self::ANSWER]);
        $profile = $this->profile(['Accountant']);

        PostCareerNewsStoriesJob::dispatchSync();
        PostCareerNewsStoriesJob::dispatchSync();

        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_CAREER_FIELDS)->count());

        $this->travel(1)->minute();
        $profile->update([
            'parsed_at' => now(),
            'profile' => [...$profile->profile, 'target_titles' => ['Software Engineer']],
        ]);

        PostCareerNewsStoriesJob::dispatchSync();

        $this->assertSame(['software_it'], $profile->fresh()->career_fields);
        $this->assertSame(2, AiUsage::where('feature', AiUsage::FEATURE_CAREER_FIELDS)->count());
    }

    public function test_nothing_is_posted_without_any_news(): void
    {
        CareerNewsAgent::fake(['']);
        $this->profile(['Accountant']);

        PostCareerNewsStoriesJob::dispatchSync();

        $this->assertSame(0, Story::count());
    }

    public function test_nothing_is_posted_without_the_official_account(): void
    {
        CareerNewsAgent::fake([self::ANSWER]);
        $this->account->delete();
        $this->profile(['Accountant']);

        PostCareerNewsStoriesJob::dispatchSync();

        CareerNewsAgent::assertNeverPrompted();
        $this->assertSame(0, Story::count());
    }

    public function test_people_only_see_news_for_their_own_field(): void
    {
        CareerNewsAgent::fake([self::ANSWER, self::ANSWER]);
        $accountant = User::find($this->profile(['Accountant'])->user_id);
        $engineer = User::find($this->profile(['Software Engineer'])->user_id);

        PostCareerNewsStoriesJob::dispatchSync();

        $finance = Story::where('career_field', 'accounting_finance')->sole();
        $software = Story::where('career_field', 'software_it')->sole();

        $this->actingAs($accountant, 'sanctum')
            ->getJson('/api/stories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $finance->id)
            ->assertJsonPath('data.0.name', 'News for you')
            ->assertJsonPath('data.0.avatar', 'https://mail.black.co.ke/android-chrome-512x512.png')
            ->assertJsonPath('data.0.text', 'Accounting & finance')
            ->assertJsonPath('data.0.media.1.source', 'The Star')
            ->assertJsonPath('data.0.hasSeen', false);

        $this->actingAs($accountant, 'sanctum')
            ->postJson("/api/stories/{$finance->id}/seen")
            ->assertOk();

        $this->actingAs($accountant, 'sanctum')
            ->getJson("/api/stories/{$finance->id}")
            ->assertJsonPath('data.hasSeen', true);

        $this->actingAs($accountant, 'sanctum')
            ->getJson("/api/stories/{$software->id}")
            ->assertNotFound();

        $this->actingAs($engineer, 'sanctum')
            ->getJson('/api/stories')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $software->id);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/stories')
            ->assertJsonCount(0, 'data');
    }

    public function test_stories_are_deleted_after_a_day(): void
    {
        CareerNewsAgent::fake([self::ANSWER]);
        $this->profile(['Accountant']);
        PostCareerNewsStoriesJob::dispatchSync();
        $image = Story::sole()->media[0]['image'];

        $this->travel(25)->hours();
        $this->artisan('model:prune', ['--model' => [Story::class]]);

        $this->assertSame(0, Story::count());
        Storage::disk('public')->assertMissing($image);
    }
}
