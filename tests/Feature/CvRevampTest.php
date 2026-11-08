<?php

namespace Tests\Feature;

use App\Agents\CvRevampAgent;
use App\Agents\CvTailorAgent;
use App\Agents\JobApplicationAgent;
use App\Enums\CvRevampStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\TailoredCvStatus;
use App\Jobs\SendMailMessageJob;
use App\Models\AiUsage;
use App\Models\CvRevamp;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Jobs\JobApplicationSender;
use App\Services\Jobs\JobMatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class CvRevampTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private JobSeekerProfile $profile;

    private const REVAMP = [
        'name' => 'Wanjiku Muhandi',
        'headline' => 'Accountant | Audit & Tax | CPA (K)',
        'contact' => ['wanjiku@example.com', '+254 700 000000', 'Nairobi, Kenya'],
        'sections' => [
            [
                'heading' => 'Profile',
                'original' => ['title' => '', 'subtitle' => '', 'period' => '', 'text' => '', 'bullets' => []],
                'revamped' => ['title' => '', 'subtitle' => '', 'period' => '', 'text' => 'CPA (K) accountant with six years in external audit.', 'bullets' => []],
                'why' => 'Adds a profile, which the CV had none of.',
            ],
            [
                'heading' => 'Experience',
                'original' => ['title' => 'Auditor', 'subtitle' => 'KPMG, Nairobi', 'period' => '2019-2025', 'text' => '', 'bullets' => ['Did audits', 'Helped with tax']],
                'revamped' => ['title' => 'Auditor', 'subtitle' => 'KPMG, Nairobi', 'period' => 'Jan 2019 - Mar 2025', 'text' => '', 'bullets' => ['• Led statutory audits of manufacturing clients', 'Prepared corporate tax computations']],
                'why' => 'Leads with results.',
            ],
            // Nothing in it, so it's dropped.
            [
                'heading' => 'Hobbies',
                'original' => ['title' => '', 'subtitle' => '', 'period' => '', 'text' => '', 'bullets' => []],
                'revamped' => ['title' => '', 'subtitle' => '', 'period' => '', 'text' => '', 'bullets' => []],
                'why' => '',
            ],
        ],
        'questions' => [
            ['section' => 2, 'question' => 'How many audit clients did you lead?'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('job-search/cv/wanjiku.pdf', '%PDF-1.4 fake');

        $this->user = User::factory()->create(['name' => 'Wanjiku Muhandi']);
        $domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'example.com', 'api_key' => 'key']);
        $account = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => 'wanjiku@example.com',
        ]);
        $this->user->update(['active_mailgun_account_id' => $account->id]);

        $this->profile = JobSeekerProfile::create([
            'user_id' => $this->user->id,
            'auto_apply' => true,
            'mode' => 'review',
            'consented_at' => now(),
            'cv_path' => 'job-search/cv/wanjiku.pdf',
            'cv_original_name' => 'Wanjiku CV.pdf',
            'cv_hash' => str_repeat('a', 64),
            'parsed_at' => now(),
            'profile' => ['full_name' => 'Wanjiku Muhandi', 'headline' => 'Accountant', 'target_titles' => ['Accountant', 'Auditor']],
            'preferences' => [],
        ]);
    }

    private function revamped(): CvRevamp
    {
        CvRevampAgent::fake([self::REVAMP]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp')
            ->assertOk()
            ->assertJsonPath('message', 'Revamping CV');

        return $this->user->cvRevamp()->sole();
    }

    private function inUse(): CvRevamp
    {
        $revamp = $this->revamped();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp/use')
            ->assertOk();

        return $revamp->fresh();
    }

    private function application(JobApplicationStatus $status = JobApplicationStatus::QUEUED, array $overrides = []): JobApplication
    {
        $posting = JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', 'Accountant'),
            'title' => 'Accountant',
            'company' => 'Acme Ltd',
            'description' => 'We need an accountant with tax experience.',
            'url' => 'https://www.myjobmag.co.ke/jobs/accountant',
            'apply_email' => 'jobs@acme.co.ke',
            'details_fetched_at' => now(),
        ]);

        return JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => $posting->id,
            'status' => $status,
            'match_score' => 90,
            'subject' => 'Application for Accountant',
            'cover_letter' => 'Dear Hiring Manager',
            ...$overrides,
        ]);
    }

    private function sentAttachmentName(JobApplication $application): string
    {
        return MailMessage::with('attachments')
            ->findOrFail($application->fresh()->mail_message_id)
            ->attachments
            ->sole()
            ->original_name;
    }

    public function test_a_cv_is_revamped_section_by_section_and_its_usage_recorded(): void
    {
        $revamp = $this->revamped();

        CvRevampAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Accountant, Auditor'));

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-search/cv-revamp')
            ->assertOk()
            ->assertJsonPath('revamp.status', 'ready')
            ->assertJsonPath('revamp.inUse', false)
            ->assertJsonPath('revamp.headline', 'Accountant | Audit & Tax | CPA (K)')
            ->assertJsonCount(2, 'revamp.sections')
            ->assertJsonPath('revamp.sections.0.original', null)
            ->assertJsonPath('revamp.sections.1.key', 's2')
            ->assertJsonPath('revamp.sections.1.revamped.bullets.0', 'Led statutory audits of manufacturing clients')
            ->assertJsonPath('revamp.sections.1.keep', 'revamped')
            ->assertJsonPath('revamp.questions.0.section', 's2')
            ->assertJsonPath('revamp.questions.0.used', false);

        $usage = AiUsage::sole();
        $this->assertSame(AiUsage::FEATURE_CV_REVAMP, $usage->feature);
        $this->assertSame($this->user->id, $usage->user_id);
        $this->assertTrue($usage->subject->is($revamp));
    }

    public function test_answers_revamp_the_cv_again_with_them(): void
    {
        $this->revamped();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/job-search/cv-revamp', ['answers' => ['q1' => 'Twelve clients a year']])
            ->assertOk()
            ->assertJsonPath('message', 'Changes Saved')
            ->assertJsonPath('revamp.questions.0.answer', 'Twelve clients a year');

        CvRevampAgent::fake([[...self::REVAMP, 'questions' => [['section' => 1, 'question' => 'Which audit software do you use?']]]]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp')
            ->assertOk()
            ->assertJsonPath('revamp.questions.0.answer', 'Twelve clients a year')
            ->assertJsonPath('revamp.questions.0.used', true)
            ->assertJsonPath('revamp.questions.1.question', 'Which audit software do you use?')
            ->assertJsonPath('revamp.questions.1.section', 's1');

        CvRevampAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Twelve clients a year'));
        $this->assertSame(2, AiUsage::where('feature', AiUsage::FEATURE_CV_REVAMP)->count());
    }

    public function test_the_chosen_sections_go_out_to_employers_once_the_revamp_is_used(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Africa/Nairobi'));
        $revamp = $this->revamped();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/job-search/cv-revamp', ['sections' => [
                's1' => ['bullets' => null, 'text' => 'CPA (K) accountant with six years in audit and tax.'],
                's2' => ['keep' => 'original'],
            ]])
            ->assertOk()
            ->assertJsonPath('revamp.sections.0.edited', true)
            ->assertJsonPath('revamp.sections.1.keep', 'original');

        $chosen = $revamp->fresh()->chosen();
        $this->assertSame('CPA (K) accountant with six years in audit and tax.', $chosen['sections'][0]['entry']['text']);
        $this->assertSame(['Did audits', 'Helped with tax'], $chosen['sections'][1]['entry']['bullets']);

        // The PDF and Word file of the CV as it stands, by signed link.
        $downloads = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-search/cv-revamp')
            ->json('revamp.downloads');

        $this->get($downloads['pdf'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get($downloads['docx'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Wanjiku Muhandi CV.docx"');
        $this->get(str($downloads['pdf'])->before('?'))
            ->assertForbidden();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp/use')
            ->assertOk()
            ->assertJsonPath('message', 'Revamped CV In Use')
            ->assertJsonPath('revamp.inUse', true);

        Storage::disk('local')->assertExists($revamp->fresh()->pdf_path);
        Storage::disk('local')->assertExists($revamp->fresh()->docx_path);

        $application = $this->application();
        $this->assertTrue(app(JobApplicationSender::class)->sendNext($this->profile->fresh()));
        $this->assertSame('Wanjiku Muhandi CV.pdf', $this->sentAttachmentName($application));

        // Back to the uploaded CV.
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/job-search/cv-revamp/use')
            ->assertOk()
            ->assertJsonPath('revamp.inUse', false);

        $this->assertSame('Wanjiku CV.pdf', $this->profile->fresh()->cvToSend()['name']);
    }

    public function test_uploading_a_new_cv_discards_the_revamp(): void
    {
        $path = $this->inUse()->pdf_path;
        Queue::fake();

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/job-search/cv', ['cv' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')])
            ->assertOk();

        $this->assertNull($this->user->cvRevamp()->first());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_revamp_needs_a_cv_that_has_been_read(): void
    {
        $this->profile->update(['parsed_at' => null]);
        CvRevampAgent::fake()->preventStrayPrompts();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp')
            ->assertJsonValidationErrors('cv');
    }

    public function test_a_failed_revamp_can_be_tried_again(): void
    {
        CvRevampAgent::fake([[...self::REVAMP, 'sections' => []]]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/cv-revamp')
            ->assertOk()
            ->assertJsonPath('revamp.status', 'failed');

        $this->assertSame(CvRevampStatus::FAILED, $this->user->cvRevamp()->sole()->status);
    }

    public function test_each_application_gets_a_cv_tailored_to_its_job_when_turned_on(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Africa/Nairobi'));

        // Tailoring starts from the revamped CV.
        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search/cv-tailoring', ['tailorCv' => true])
            ->assertJsonValidationErrors('tailorCv');

        $this->inUse();

        $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/job-search/cv-tailoring', ['tailorCv' => true])
            ->assertOk()
            ->assertJsonPath('tailorCv', true);

        $this->profile->update(['mode' => 'autopilot']);
        JobApplicationAgent::fake([['subject' => 'Application for Accountant', 'body' => 'Dear Hiring Manager']]);
        CvTailorAgent::fake([[
            'headline' => 'Accountant | Corporate Tax | CPA (K)',
            'sections' => [
                ['key' => 's2', 'text' => '', 'bullets' => ['Prepared corporate tax computations', 'Led statutory audits of manufacturing clients']],
                // Not a section of the CV.
                ['key' => 's9', 'text' => 'Invented', 'bullets' => []],
            ],
        ]]);

        $application = $this->application(JobApplicationStatus::PENDING_APPROVAL, ['subject' => null, 'cover_letter' => null]);
        app(JobMatcher::class)->draft($this->profile->fresh(), $application->load('posting'));

        $application->refresh();
        $this->assertSame(TailoredCvStatus::READY, $application->cv_status);
        $this->assertSame('Accountant | Corporate Tax | CPA (K)', $application->cv['headline']);
        $this->assertSame('Prepared corporate tax computations', $application->cv['sections'][1]['entry']['bullets'][0]);
        $this->assertSame('Jan 2019 - Mar 2025', $application->cv['sections'][1]['entry']['period']);
        CvTailorAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'We need an accountant with tax experience.'));
        $this->assertTrue(AiUsage::where('feature', AiUsage::FEATURE_CV_TAILOR)->sole()->subject->is($application));

        $this->assertTrue(app(JobApplicationSender::class)->sendNext($this->profile->fresh()));
        $message = MailMessage::with('attachments')->findOrFail($application->fresh()->mail_message_id);
        $this->assertSame(
            Storage::disk('local')->get($application->cv_path),
            Storage::disk('local')->get($message->attachments->sole()->path),
        );
    }

    public function test_sending_waits_for_a_cv_being_tailored_but_not_for_ever(): void
    {
        Queue::fake([SendMailMessageJob::class]);
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Africa/Nairobi'));
        $this->inUse();
        $application = $this->application(overrides: ['cv_status' => TailoredCvStatus::TAILORING]);

        $this->assertFalse(app(JobApplicationSender::class)->sendNext($this->profile->fresh()));

        $this->travel(31)->minutes();

        $this->assertTrue(app(JobApplicationSender::class)->sendNext($this->profile->fresh()));
        $this->assertSame('Wanjiku Muhandi CV.pdf', $this->sentAttachmentName($application));
    }

    public function test_one_application_can_be_tailored_on_request(): void
    {
        $this->inUse();
        $application = $this->application(JobApplicationStatus::APPLY_MANUALLY);
        CvTailorAgent::fake([['headline' => 'Tax Accountant', 'sections' => []]]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/job-applications/{$application->id}/cv")
            ->assertOk()
            ->assertJsonPath('message', 'Tailoring CV');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=manual')
            ->assertOk()
            ->assertJsonPath('data.0.cv.status', 'ready');

        $url = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications?tab=manual')
            ->json('data.0.cv.url');

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
