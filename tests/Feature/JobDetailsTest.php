<?php

namespace Tests\Feature;

use App\Agents\JobSalaryAgent;
use App\Enums\JobApplicationStatus;
use App\Jobs\EstimateJobSalaryJob;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Jobs\StructuredJobData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class JobDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function application(array $posting = []): JobApplication
    {
        $posting = JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', $posting['title'] ?? 'Accountant'),
            'title' => 'Accountant',
            'company' => 'Acme Ltd',
            'location' => 'Nairobi',
            'description' => "We need an accountant.\n\nResponsibilities:\nKeep the books.",
            'url' => 'https://www.myjobmag.co.ke/jobs/accountant-at-acme',
            ...$posting,
        ]);

        return JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => $posting->id,
            'status' => JobApplicationStatus::PENDING_APPROVAL,
            'match_score' => 88,
        ]);
    }

    public function test_opening_a_job_shows_the_whole_posting_and_prices_it_once(): void
    {
        Queue::fake([EstimateJobSalaryJob::class]);
        $application = $this->application();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('description', "We need an accountant.\n\nResponsibilities:\nKeep the books.")
            ->assertJsonPath('salary', null)
            ->assertJsonPath('salaryStatus', 'estimating');

        // Opened again while the AI is still working: not asked twice.
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk();

        Queue::assertPushed(EstimateJobSalaryJob::class, 1);
    }

    public function test_the_estimate_is_kept_on_the_posting_and_shown_on_the_card(): void
    {
        JobSalaryAgent::fake([[
            'stated' => false,
            'min' => 150000,
            'max' => 90000,
            'currency' => 'kes',
            'period' => 'month',
            'basis' => 'Typical for mid-level accountants in Nairobi.',
        ]]);
        $application = $this->application();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk();

        JobSalaryAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Keep the books.'));

        $salary = [
            'min' => 90000,
            'max' => 150000,
            'currency' => 'KES',
            'period' => 'month',
            'stated' => false,
            'basis' => 'Typical for mid-level accountants in Nairobi.',
        ];

        // MySQL keeps JSON keys in its own order.
        $this->assertEquals($salary, $application->posting->fresh()->salary);
        $this->assertSame(1, AiUsage::where('feature', AiUsage::FEATURE_JOB_SALARY)->whereNull('user_id')->count());

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('salaryStatus', 'ready')
            ->assertJson(['salary' => $salary]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=review')
            ->assertOk()
            ->assertJson(['data' => [['posting' => ['salary' => $salary]]]]);
    }

    public function test_a_job_too_vague_to_price_is_not_asked_about_again(): void
    {
        JobSalaryAgent::fake([['stated' => false, 'min' => 0, 'max' => 0, 'currency' => '', 'period' => 'month', 'basis' => '']]);
        $application = $this->application();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk();

        Queue::fake([EstimateJobSalaryJob::class]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('salary', null)
            ->assertJsonPath('salaryStatus', 'unknown');

        Queue::assertNothingPushed();
    }

    public function test_a_salary_the_job_board_publishes_is_used_without_the_ai(): void
    {
        Queue::fake([EstimateJobSalaryJob::class]);
        $json = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Accountant',
            'description' => 'Keep the books.',
            'baseSalary' => [
                '@type' => 'MonetaryAmount',
                'currency' => 'KES',
                'value' => ['@type' => 'QuantitativeValue', 'minValue' => 80000, 'maxValue' => 120000, 'unitText' => 'MONTH'],
            ],
        ]);

        $details = app(StructuredJobData::class)
            ->read("<script type=\"application/ld+json\">{$json}</script>", 'www.myjobmag.co.ke');

        $this->assertEquals(
            ['min' => 80000, 'max' => 120000, 'currency' => 'KES', 'period' => 'month', 'stated' => true, 'basis' => null],
            $details['salary'],
        );

        $application = $this->application(['salary' => $details['salary']]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('salary.stated', true)
            ->assertJsonPath('salaryStatus', 'ready');

        Queue::assertNothingPushed();
    }

    public function test_someone_elses_job_cannot_be_opened(): void
    {
        $application = $this->application();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/job-applications/{$application->id}")
            ->assertNotFound();
    }
}
