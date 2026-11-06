<?php

namespace Tests\Feature;

use App\Agents\ApplicationFormAgent;
use App\Enums\ApplicationFormStatus;
use App\Enums\JobApplicationStatus;
use App\Jobs\PrepareApplicationFormJob;
use App\Jobs\SubmitApplicationFormJob;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\User;
use App\Services\Jobs\ApplicationForms\ApplicationFormService;
use App\Services\Jobs\MyJobMagSource;
use App\Services\Jobs\SafePageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class ApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private const FIELDS = [
        ['key' => 'text-firstname', 'type' => 'text', 'label' => 'First Name', 'required' => true, 'options' => []],
        ['key' => 'text-email', 'type' => 'text', 'label' => 'Email', 'required' => true, 'options' => []],
        ['key' => 'tel-phone', 'type' => 'tel', 'label' => 'Phone', 'required' => true, 'options' => []],
        ['key' => 'textarea-why', 'type' => 'textarea', 'label' => 'Why do you want this job?', 'required' => true, 'options' => []],
        ['key' => 'radio-right-to-work', 'type' => 'radio', 'label' => 'Do you have the right to work in Kenya?', 'required' => false, 'options' => ['Yes', 'No']],
        ['key' => 'file-resume', 'type' => 'file', 'label' => 'Resume', 'required' => true, 'options' => []],
        ['key' => 'file-cover', 'type' => 'file', 'label' => 'Cover Letter', 'required' => false, 'options' => []],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->app->instance(SafePageFetcher::class, new class extends SafePageFetcher
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        $this->user = User::factory()->create(['name' => 'Wanjiku Muhandi']);
        $domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'example.com', 'api_key' => 'key']);
        $account = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => 'wanjiku@example.com',
        ]);
        $this->user->update(['active_mailgun_account_id' => $account->id]);

        Storage::disk('local')->put('job-search/cv/wanjiku.pdf', '%PDF-1.4 fake');

        JobSeekerProfile::create([
            'user_id' => $this->user->id,
            'auto_apply' => true,
            'consented_at' => now(),
            'cv_path' => 'job-search/cv/wanjiku.pdf',
            'cv_original_name' => 'Wanjiku CV.pdf',
            'profile' => ['full_name' => 'Wanjiku Muhandi', 'headline' => 'Accountant', 'location' => 'Nairobi'],
            'preferences' => ['locations' => ['Nairobi']],
        ]);
    }

    private function manualApplication(): JobApplication
    {
        $posting = JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', 'Accountant'),
            'title' => 'Accountant',
            'company' => 'Acme Ltd',
            'description' => 'We need an accountant.',
            'url' => 'https://www.myjobmag.co.ke/jobs/accountant-at-acme',
            'apply_url' => 'https://acme.bamboohr.com/careers/12',
            'details_fetched_at' => now(),
        ]);

        return JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => $posting->id,
            'status' => JobApplicationStatus::APPLY_MANUALLY,
        ]);
    }

    private function fakeBrowser(array $result): void
    {
        Process::fake(['*' => Process::result(json_encode($result))]);
    }

    private function prepared(?string $captcha = null): JobApplication
    {
        $application = $this->manualApplication();

        $this->fakeBrowser(['url' => 'https://acme.bamboohr.com/careers/12', 'adapter' => 'generic', 'captcha' => $captcha, 'blocker' => null, 'fields' => self::FIELDS]);
        ApplicationFormAgent::fake([['answers' => [
            ['key' => 'tel-phone', 'answer' => '', 'source' => 'unknown', 'note' => ''],
            ['key' => 'textarea-why', 'answer' => 'I have six years in audit.', 'source' => 'drafted', 'note' => ''],
            // Not one of the choices, so it's left for the user.
            ['key' => 'radio-right-to-work', 'answer' => 'Probably', 'source' => 'known', 'note' => ''],
        ]]]);

        $application->update(['form_status' => ApplicationFormStatus::PREPARING]);
        (new PrepareApplicationFormJob($application->id))->handle(app(ApplicationFormService::class));

        return $application->fresh();
    }

    private function field(JobApplication $application, string $key): array
    {
        return collect($application->form['fields'])->firstWhere('key', $key);
    }

    public function test_preparing_reads_the_form_in_the_background(): void
    {
        Queue::fake();
        $application = $this->manualApplication();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/form")
            ->assertOk()
            ->assertJsonPath('message', 'Preparing Application')
            ->assertJsonPath('data.form.status', 'preparing');

        Queue::assertPushedOn('browser', PrepareApplicationFormJob::class);
    }

    public function test_each_question_gets_an_answer_and_how_much_the_user_has_to_do(): void
    {
        $application = $this->prepared();

        Process::assertRan(fn (PendingProcess $process) => str_contains($process->input, '"mode":"inspect"')
            && str_contains($process->input, 'acme.bamboohr.com\/careers\/12'));
        ApplicationFormAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Why do you want this job?')
            && ! str_contains($prompt->prompt, 'text-firstname'));

        $this->assertSame(ApplicationFormStatus::READY, $application->form_status);
        $this->assertSame(['answer' => 'Wanjiku', 'effort' => 'filled'], collect($this->field($application, 'text-firstname'))->only(['answer', 'effort'])->all());
        $this->assertSame('wanjiku@example.com', $this->field($application, 'text-email')['answer']);
        $this->assertSame('needs_you', $this->field($application, 'tel-phone')['effort']);
        $this->assertSame('check', $this->field($application, 'textarea-why')['effort']);
        $this->assertSame(['answer' => '', 'effort' => 'optional'], collect($this->field($application, 'radio-right-to-work'))->only(['answer', 'effort'])->all());
        $this->assertSame(['answer' => 'cv', 'effort' => 'filled'], collect($this->field($application, 'file-resume'))->only(['answer', 'effort'])->all());
        $this->assertSame('optional', $this->field($application, 'file-cover')['effort']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=manual')
            ->assertOk()
            ->assertJsonPath('data.0.form.minutesLeft', 3)
            ->assertJsonPath('data.0.form.fields.2.effort', 'needs_you');
    }

    public function test_forms_behind_a_sign_in_say_so(): void
    {
        $application = $this->manualApplication();
        $this->fakeBrowser(['url' => 'https://join.com/apply', 'blocker' => 'sign_in', 'fields' => []]);
        ApplicationFormAgent::fake()->preventStrayPrompts();

        $application->update(['form_status' => ApplicationFormStatus::PREPARING]);
        (new PrepareApplicationFormJob($application->id))->handle(app(ApplicationFormService::class));

        $this->assertSame(ApplicationFormStatus::BLOCKED, $application->fresh()->form_status);
        $this->assertStringContainsString('sign in', $application->fresh()->form_error);
    }

    public function test_submitting_needs_every_required_answer_and_remembers_typed_ones(): void
    {
        Queue::fake();
        $application = $this->prepared();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/form/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers' => 'Phone']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/form/submit", ['answers' => ['tel-phone' => '+254 700 000 000']])
            ->assertOk()
            ->assertJsonPath('message', 'Submitting Application');

        Queue::assertPushedOn('browser', SubmitApplicationFormJob::class);
        $this->assertSame('+254 700 000 000', $this->user->jobSeekerProfile->fresh()->form_answers['phone']['answer']);
    }

    public function test_a_submitted_form_counts_as_applied(): void
    {
        $application = $this->prepared();
        $application->update(['form_status' => ApplicationFormStatus::SUBMITTING]);
        $this->fakeBrowser(['submitted' => true, 'url' => 'https://acme.bamboohr.com/thanks']);

        (new SubmitApplicationFormJob($application->id))->handle(app(ApplicationFormService::class));

        Process::assertRan(fn (PendingProcess $process) => str_contains($process->input, '"mode":"submit"')
            && str_contains($process->input, 'Wanjiku CV.pdf')
            && str_contains($process->input, '"textarea-why":"I have six years in audit."'));

        $application->refresh();
        $this->assertSame(JobApplicationStatus::APPLIED, $application->status);
        $this->assertSame(ApplicationFormStatus::SUBMITTED, $application->form_status);
        $this->assertNotNull($application->applied_at);
    }

    public function test_a_rejected_submission_keeps_the_answers_to_try_again(): void
    {
        $application = $this->prepared();
        $application->update(['form_status' => ApplicationFormStatus::SUBMITTING]);
        $this->fakeBrowser(['submitted' => false, 'error' => "The form wasn't accepted: Phone is invalid"]);

        (new SubmitApplicationFormJob($application->id))->handle(app(ApplicationFormService::class));

        $application->refresh();
        $this->assertSame(JobApplicationStatus::APPLY_MANUALLY, $application->status);
        $this->assertSame(ApplicationFormStatus::SUBMIT_FAILED, $application->form_status);
        $this->assertStringContainsString('Phone is invalid', $application->form_error);
    }

    public function test_a_captcha_stops_the_submit_unless_a_solving_service_is_set_up(): void
    {
        Queue::fake();
        $application = $this->prepared('recaptcha_v3');
        $answers = ['answers' => ['tel-phone' => '0700000000']];

        config(['job_search.application_forms.captcha.key' => null]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/form/submit", $answers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['form' => 'CAPTCHA']);

        config(['job_search.application_forms.captcha.key' => 'test-key']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/form/submit", $answers)
            ->assertOk();
    }

    public function test_an_answer_typed_once_fills_the_same_question_on_the_next_form(): void
    {
        $this->user->jobSeekerProfile->update(['form_answers' => ['phone' => ['label' => 'Phone', 'answer' => '0711 111 111']]]);

        $application = $this->prepared();

        $this->assertSame(['answer' => '0711 111 111', 'effort' => 'filled'], collect($this->field($application, 'tel-phone'))->only(['answer', 'effort'])->all());
    }

    public function test_screenshots_open_only_by_signed_link(): void
    {
        $application = $this->prepared();
        Storage::disk('local')->put("application-forms/{$application->id}/form.png", 'png');

        $link = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=manual')
            ->json('data.0.form.screenshots.form');

        $this->get($link)->assertOk();
        $this->get("/api/job-applications/{$application->id}/form/screenshots/form")->assertForbidden();
    }

    public function test_myjobmag_pages_give_the_link_to_the_employers_form(): void
    {
        $details = app(MyJobMagSource::class)->parseJobPage(<<<'HTML'
            <h2 id="application-method">Method of Application</h2>
            <div>Interested and qualified? Go to <a href="/apply-now/1376359">Caava Group on turnkeyafrica.bamboohr.com</a> to apply</div>
            HTML);

        $this->assertSame('https://www.myjobmag.co.ke/apply-now/1376359', $details['apply_url']);
    }
}
