<?php

namespace Tests\Feature;

use App\Enums\JobApplicationStatus;
use App\Http\Services\MailgunMailboxService;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Users without a mailbox get an address on the app's own domain to apply
 * for jobs from.
 */
class JobSearchMailboxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Addresses already on Mailgun. */
    private array $onMailgun = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mailgun.domain' => 'mail.black.co.ke',
            'services.mailgun.secret' => 'platform-key',
        ]);

        $mailboxes = Mockery::mock(MailgunMailboxService::class);
        $mailboxes->shouldReceive('mailboxAddresses')->andReturnUsing(fn () => collect($this->onMailgun));
        $mailboxes->shouldReceive('createNewMailbox')->andReturnUsing(
            fn (MailgunDomain $domain, string $address) => in_array($address, $this->onMailgun, true) ? null : 'smtp-password'
        );
        $this->app->instance(MailgunMailboxService::class, $mailboxes);

        $this->user = User::factory()->create(['name' => 'José Ng\'ang\'a Kamau']);
        JobSeekerProfile::create([
            'user_id' => $this->user->id,
            'cv_path' => 'job-search/cv/jose.pdf',
            'daily_limit' => 5,
            'min_match_score' => 75,
            'preferences' => [],
        ]);
    }

    private function platformAccount(): MailgunAccount
    {
        $account = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => MailgunDomain::platform()->id,
            'mailbox_address' => 'josekamau@mail.black.co.ke',
        ]);
        $this->user->update(['active_mailgun_account_id' => $account->id]);

        return $account;
    }

    public function test_suggestions_are_made_from_the_name_and_skip_taken_addresses(): void
    {
        $other = User::factory()->create();
        MailgunAccount::create([
            'user_id' => $other->id,
            'mailgun_domain_id' => MailgunDomain::platform()->id,
            'mailbox_address' => 'josekamau@mail.black.co.ke',
        ]);
        // A deleted account's address stays on Mailgun, so it's never reused.
        $this->onMailgun = ['jose.kamau@mail.black.co.ke'];

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-search/mailbox')
            ->assertOk()
            ->assertExactJson(['suggestions' => [
                'jkamau@mail.black.co.ke',
                'josekamau2@mail.black.co.ke',
                'josekamau3@mail.black.co.ke',
            ]]);
    }

    public function test_reserved_names_are_never_suggested(): void
    {
        $this->user->update(['name' => 'Support']);

        $suggestions = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-search/mailbox')
            ->json('suggestions');

        $this->assertSame('support2@mail.black.co.ke', $suggestions[0]);
    }

    public function test_the_chosen_address_becomes_the_users_mail_account(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/mailbox', ['address' => 'JoseKamau@mail.black.co.ke', 'consent' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Address Created')
            ->assertJsonPath('account.mailboxAddress', 'josekamau@mail.black.co.ke')
            ->assertJsonPath('account.isPlatform', true)
            ->assertJsonPath('needsMailbox', false);

        $account = $this->user
            ->fresh()
            ->activeMailgunAccount;

        $this->assertSame('josekamau@mail.black.co.ke', $account->mailbox_address);
        $this->assertTrue($account->isPlatform());
        $this->assertNull($account->mailgunDomain->user_id);
        // The app's domain isn't listed among the user's own domains.
        $this->assertSame(0, $this->user->mailgunDomains()->count());
    }

    public function test_an_address_needs_consent_a_cv_and_must_be_one_of_the_suggestions(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/mailbox', ['address' => 'josekamau@mail.black.co.ke'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/mailbox', ['address' => 'ceo@mail.black.co.ke', 'consent' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address');

        $this->user->jobSeekerProfile->update(['cv_path' => null]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/mailbox', ['address' => 'josekamau@mail.black.co.ke', 'consent' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.address.0', 'Upload your CV first.');

        $this->assertSame(0, MailgunAccount::count());
    }

    public function test_only_one_address_is_created_per_user(): void
    {
        $this->platformAccount();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-search/mailbox')
            ->assertExactJson(['suggestions' => []]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/job-search/mailbox', ['address' => 'jose.kamau@mail.black.co.ke', 'consent' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address');
    }

    public function test_the_address_cannot_be_removed(): void
    {
        $account = $this->platformAccount();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/settings/mailgun-accounts/{$account->id}")
            ->assertUnprocessable();

        $this->assertModelExists($account);
    }

    public function test_the_address_only_sends_job_applications_and_replies_to_them(): void
    {
        $this->platformAccount();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', [
                'to' => ['friend@example.com'],
                'subject' => 'Hi',
                'bodyHtml' => '<p>Hi</p>',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mailbox_address');

        $thread = MailThread::create(['user_id' => $this->user->id, 'subject' => 'Application for Accountant']);
        $reply = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'direction' => 'inbound',
            'folder' => 'inbox',
            'from_address' => ['address' => 'hr@acme.co.ke'],
            'to' => [['address' => 'josekamau@mail.black.co.ke']],
            'subject' => 'Re: Application for Accountant',
            'status' => 'received',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/messages/{$reply->id}/reply", ['subject' => 'Re: Hi', 'bodyHtml' => '<p>Thanks</p>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mailbox_address');

        JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => JobPosting::create([
                'source' => JobPosting::SOURCE_BOARD,
                'fingerprint' => JobPosting::fingerprint('test', 'Accountant', 'Acme Ltd'),
                'title' => 'Accountant',
                'company' => 'Acme Ltd',
                'url' => 'https://acme.co.ke/jobs/accountant',
            ])->id,
            'status' => JobApplicationStatus::APPLIED,
            'mail_thread_id' => $thread->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/messages/{$reply->id}/reply", ['subject' => 'Re: Hi', 'bodyHtml' => '<p>Thanks</p>'])
            ->assertSuccessful();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/messages/{$reply->id}/forward", [
                'to' => ['friend@example.com'],
                'subject' => 'Fwd: Hi',
                'bodyHtml' => '<p>Look</p>',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mailbox_address');
    }
}
