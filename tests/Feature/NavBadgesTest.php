<?php

namespace Tests\Feature;

use App\Enums\JobApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Notifications\JobApplicationFailedNotification;
use App\Notifications\JobsToApplyManuallyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counts beside the nav links, and where notifications take you.
 */
class NavBadgesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $domain = MailgunDomain::create(['user_id' => $this->user->id, 'domain' => 'example.com', 'api_key' => 'key']);
        $account = MailgunAccount::create([
            'user_id' => $this->user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => 'me@example.com',
        ]);
        $this->user->update(['active_mailgun_account_id' => $account->id]);
    }

    private function thread(string $folder, bool $read, string $to = 'me@example.com'): MailThread
    {
        $thread = MailThread::create(['user_id' => $this->user->id, 'subject' => 'Hello', 'has_unread' => ! $read]);

        MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'direction' => 'inbound',
            'folder' => $folder,
            'from_address' => ['address' => 'friend@example.org'],
            'to' => [['address' => $to]],
            'subject' => 'Hello',
            'status' => 'received',
            'is_read' => $read,
        ]);

        return $thread;
    }

    private function application(JobApplicationStatus $status): JobApplication
    {
        $title = 'Job '.JobPosting::count();

        return JobApplication::create([
            'user_id' => $this->user->id,
            'job_posting_id' => JobPosting::create([
                'source' => JobPosting::SOURCE_BOARD,
                'fingerprint' => JobPosting::fingerprint('test', $title, 'Acme'),
                'title' => $title,
                'company' => 'Acme',
                'url' => 'https://acme.co.ke/'.str($title)->slug(),
            ])->id,
            'status' => $status,
        ]);
    }

    public function test_the_inbox_badge_counts_unread_conversations_in_the_active_mailbox(): void
    {
        $this->thread('inbox', read: false);
        $this->thread('inbox', read: false);
        $this->thread('inbox', read: true);
        $this->thread('archive', read: false);
        $this->thread('inbox', read: false, to: 'other@example.com');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/threads/unread-count')
            ->assertOk()
            ->assertExactJson(['count' => 2]);
    }

    public function test_job_application_badges_count_each_tab(): void
    {
        $this->application(JobApplicationStatus::PENDING_APPROVAL);
        $this->application(JobApplicationStatus::PENDING_APPROVAL);
        $this->application(JobApplicationStatus::APPLIED);
        $this->application(JobApplicationStatus::REPLIED);
        $this->application(JobApplicationStatus::APPLY_MANUALLY);
        $this->application(JobApplicationStatus::FAILED);
        $this->application(JobApplicationStatus::NOT_MATCHED);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/job-applications/counts')
            ->assertOk()
            ->assertExactJson(['counts' => ['review' => 2, 'queued' => 0, 'sent' => 2, 'manual' => 1, 'closed' => 1]]);
    }

    public function test_notifications_open_their_tab(): void
    {
        $application = $this->application(JobApplicationStatus::FAILED);

        $this->assertSame('/job-applications/manual', (new JobsToApplyManuallyNotification(2))->toArray($this->user)['url']);
        $this->assertSame('/job-applications/closed', (new JobApplicationFailedNotification($application))->toArray($this->user)['url']);
    }
}
