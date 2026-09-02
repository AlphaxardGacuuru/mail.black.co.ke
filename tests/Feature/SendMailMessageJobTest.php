<?php

namespace Tests\Feature;

use App\Enums\MailStatus;
use App\Jobs\SendMailMessageJob;
use App\Mail\ComposedMail;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMailMessageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOutboundMessage(User $user, array $overrides = []): MailMessage
    {
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Project update']);

        return MailMessage::create(array_merge([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'subject' => 'Project update',
            'to' => [['address' => 'recipient@example.com', 'name' => null]],
            'status' => MailStatus::QUEUED->value,
        ], $overrides));
    }

    public function test_it_sends_and_claims_the_message_with_its_job_id(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $mailMessage = $this->makeOutboundMessage($user);

        $job = new TestableSendMailMessageJob($mailMessage->id);
        $job->fakeJobId = 'job-1';
        $job->handle();

        Mail::assertSent(ComposedMail::class);
        $mailMessage->refresh();
        $this->assertSame(MailStatus::SENT->value, $mailMessage->status);
        $this->assertSame('job-1', $mailMessage->job_id);
    }

    public function test_a_different_job_id_cannot_claim_an_already_claimed_message(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $mailMessage = $this->makeOutboundMessage($user, ['job_id' => 'job-1']);

        $job = new TestableSendMailMessageJob($mailMessage->id);
        $job->fakeJobId = 'job-2';
        $job->handle();

        Mail::assertNothingSent();
        $mailMessage->refresh();
        $this->assertSame(MailStatus::QUEUED->value, $mailMessage->status);
        $this->assertSame('job-1', $mailMessage->job_id);
    }

    public function test_the_same_job_id_can_reclaim_its_own_message(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $mailMessage = $this->makeOutboundMessage($user, ['job_id' => 'job-1']);

        $job = new TestableSendMailMessageJob($mailMessage->id);
        $job->fakeJobId = 'job-1';
        $job->handle();

        Mail::assertSent(ComposedMail::class);
        $mailMessage->refresh();
        $this->assertSame(MailStatus::SENT->value, $mailMessage->status);
    }

    public function test_it_does_not_resend_an_already_sent_message(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $mailMessage = $this->makeOutboundMessage($user, [
            'status' => MailStatus::SENT->value,
            'job_id' => 'job-1',
        ]);

        $job = new TestableSendMailMessageJob($mailMessage->id);
        $job->fakeJobId = 'job-2';
        $job->handle();

        Mail::assertNothingSent();
    }
}

class TestableSendMailMessageJob extends SendMailMessageJob
{
    public ?string $fakeJobId = null;

    protected function currentJobId(): ?string
    {
        return $this->fakeJobId;
    }
}
