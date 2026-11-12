<?php

namespace Tests\Feature;

use App\Enums\JobApplicationStatus;
use App\Http\Services\MailgunMailboxService;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailAttachment;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mailgun\Api\Domain;
use Mailgun\Mailgun;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Deleting an account, by its owner or an admin, takes everything in it
 * (job search included) but keeps its AI usage.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MockInterface $mailgun;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create();

        $this->mailgun = Mockery::mock(Domain::class);

        $client = Mockery::mock(Mailgun::class);
        $client->shouldReceive('domains')->andReturn($this->mailgun);

        $service = Mockery::mock(MailgunMailboxService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->andReturn($client);

        $this->app->instance(MailgunMailboxService::class, $service);
    }

    /**
     * A job seeker with a mailbox of their own, a job search address, mail
     * with an attachment, a CV, an application and AI usage.
     */
    private function jobSeeker(): JobApplication
    {
        $domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'money.black.co.ke', 'api_key' => 'key']);
        MailgunAccount::create(['user_id' => $this->user->id, 'mailgun_domain_id' => $domain->id, 'mailbox_address' => 'al@money.black.co.ke']);
        $address = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => MailgunDomain::platform()->id,
            'mailbox_address' => 'al@mail.black.co.ke',
        ]);

        $thread = MailThread::create(['user_id' => $this->user->id, 'subject' => 'Interview']);
        $message = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'direction' => 'inbound',
            'folder' => 'inbox',
            'from_address' => ['address' => 'hr@acme.co.ke'],
            'to' => [['address' => 'al@mail.black.co.ke']],
            'subject' => 'Interview',
        ]);
        Storage::disk('public')->put("mail-attachments/{$message->id}/invite.pdf", 'pdf');
        MailAttachment::create([
            'mail_message_id' => $message->id,
            'disk' => 'public',
            'path' => "mail-attachments/{$message->id}/invite.pdf",
            'original_name' => 'invite.pdf',
            'mime_type' => 'application/pdf',
            'size' => 3,
        ]);

        Storage::disk('local')->put("job-search/cv/{$this->user->id}/cv.pdf", 'cv');
        JobSeekerProfile::create([
            'user_id' => $this->user->id,
            'auto_apply' => true,
            'mode' => 'review',
            'mailgun_account_id' => $address->id,
            'cv_path' => "job-search/cv/{$this->user->id}/cv.pdf",
            'preferences' => [],
            'daily_limit' => 5,
            'min_match_score' => 75,
        ]);

        $posting = JobPosting::create([
            'source' => JobPosting::SOURCE_BOARD,
            'fingerprint' => JobPosting::fingerprint('test', 'Accountant'),
            'title' => 'Accountant',
            'company' => 'Acme Ltd',
        ]);
        $application = JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => $posting->id,
            'mail_thread_id' => $thread->id,
            'status' => JobApplicationStatus::APPLY_MANUALLY,
        ]);
        Storage::disk('local')->put("application-forms/{$application->id}/form.png", 'png');

        AiUsage::create([
            'user_id' => $this->user->id,
            'feature' => AiUsage::FEATURE_JOB_MATCH,
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite',
            'cost_micros' => 1200,
        ]);

        $this->user->notify(new WelcomeNotification);
        $this->user->createToken('web');

        return $application;
    }

    private function assertEverythingDeleted(JobApplication $application): void
    {
        $this->assertModelMissing($this->user);
        $this->assertSame(0, MailgunAccount::count());
        $this->assertSame(0, MailMessage::count());
        $this->assertSame(0, JobSeekerProfile::count());
        $this->assertModelMissing($application);
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        Storage::disk('public')->assertDirectoryEmpty('mail-attachments');
        Storage::disk('local')->assertMissing("job-search/cv/{$this->user->id}");
        Storage::disk('local')->assertMissing("application-forms/{$application->id}");

        // Kept for the monthly costs, under the deleted user's id.
        $this->assertSame(1, AiUsage::where('user_id', $this->user->id)->count());
    }

    public function test_an_admin_can_delete_an_account_with_job_search(): void
    {
        $application = $this->jobSeeker();

        // Their own mailbox goes; the job search address stays on Mailgun
        // so it's never given to anyone else.
        $this->mailgun->shouldReceive('deleteCredential')
            ->once()
            ->with('money.black.co.ke', 'al@money.black.co.ke');

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->deleteJson("/api/admin/users/{$this->user->id}")
            ->assertOk()
            ->assertJson(['message' => 'Account Deleted']);

        $this->assertEverythingDeleted($application);
    }

    public function test_people_can_delete_their_own_account_with_job_search(): void
    {
        $application = $this->jobSeeker();

        $this->mailgun->shouldReceive('deleteCredential')
            ->once();

        $this->actingAs($this->user)
            ->deleteJson(route('profile.destroy'), ['password' => $this->user->email])
            ->assertOk()
            ->assertJson(['message' => 'Account Deleted']);

        $this->assertGuest('web');
        $this->assertEverythingDeleted($application);
    }

    public function test_a_mailbox_mailgun_wont_delete_doesnt_stop_the_account_going(): void
    {
        $application = $this->jobSeeker();

        $this->mailgun->shouldReceive('deleteCredential')
            ->andThrow(new \RuntimeException('Mailgun is down'));

        $this->actingAs($this->user)
            ->deleteJson(route('profile.destroy'), ['password' => $this->user->email])
            ->assertOk();

        $this->assertEverythingDeleted($application);
    }

    public function test_google_sign_ups_confirm_with_their_email(): void
    {
        $this->user->update(['google_id' => '123']);

        $this->actingAs($this->user)
            ->deleteJson(route('profile.destroy'), ['email' => 'someone@else.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Type your email address exactly as shown.']);

        $this->assertModelExists($this->user);

        $this->actingAs($this->user)
            ->deleteJson(route('profile.destroy'), ['email' => $this->user->email])
            ->assertOk();

        $this->assertModelMissing($this->user);
    }

    public function test_deleting_an_account_keeps_the_shared_default_avatar_and_removes_an_upload(): void
    {
        Storage::disk('public')->put('avatars/male-avatar.png', 'default');
        Storage::disk('public')->put('avatars/uploads/me.jpg', 'mine');

        $admin = User::factory()->create(['email' => config('admin.email')]);
        $defaultUser = User::factory()->create(['avatar' => 'avatars/male-avatar.png']);
        $uploader = User::factory()->create(['avatar' => 'avatars/uploads/me.jpg']);

        collect([$defaultUser, $uploader])
            ->each(fn (User $user) => $this->actingAs($admin, 'sanctum')
                ->deleteJson("/api/admin/users/{$user->id}")
                ->assertOk());

        Storage::disk('public')
            ->assertExists('avatars/male-avatar.png')
            ->assertMissing('avatars/uploads/me.jpg');
    }

    public function test_admins_delete_their_own_account_from_their_settings(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable();

        $this->assertModelExists($admin);
    }

    public function test_only_admins_can_delete_other_accounts(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/users/{$this->user->id}")
            ->assertForbidden();

        $this->assertModelExists($this->user);
    }
}
