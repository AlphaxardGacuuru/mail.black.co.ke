<?php

namespace App\Services\Jobs;

use App\Enums\JobApplicationStatus;
use App\Enums\MailFolder;
use App\Enums\MailStatus;
use App\Events\JobApplicationSentEvent;
use App\Http\Services\Concerns\ResolvesMailThread;
use App\Http\Services\MailSanitizerService;
use App\Jobs\SendMailMessageJob;
use App\Models\JobApplication;
use App\Models\JobSeekerProfile;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Notifications\JobSearchPausedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sends queued applications from the user's mailbox with their CV
 * attached. Every guardrail is checked again right before sending, so
 * switching job search off stops anything not yet sent.
 */
class JobApplicationSender
{
    use ResolvesMailThread;

    public function __construct(
        protected JobMatcher $matcher,
        protected MailSanitizerService $sanitizer,
    ) {}

    /**
     * Send the user's next queued application, if today's allowance and the
     * sending hours allow it. Returns whether one was sent.
     */
    public function sendNext(JobSeekerProfile $profile): bool
    {
        if (! $profile->isReady() || ! $this->withinSendingHours() || $this->pauseIfBouncing($profile)) {
            return false;
        }

        if ($this->sentToday($profile) >= min($profile->daily_limit, (int) config('job_search.max_daily_limit'))) {
            return false;
        }

        $application = $profile->user->jobApplications()
            ->where('status', JobApplicationStatus::QUEUED)
            ->whereNotNull('cover_letter')
            ->with('posting')
            ->orderByDesc('match_score')
            ->first();

        return $application ? $this->send($profile, $application) : false;
    }

    protected function send(JobSeekerProfile $profile, JobApplication $application): bool
    {
        $posting = $application->posting;
        $account = $profile->sendingAccount();

        if (! $posting?->apply_email || ! $profile->cv_path || ! Storage::disk('local')->exists($profile->cv_path)) {
            $application->update(['status' => JobApplicationStatus::APPLY_MANUALLY]);

            return false;
        }

        if ($this->matcher->appliedToCompanyRecently($profile, $posting, $application->id)) {
            $application->update([
                'status' => JobApplicationStatus::SKIPPED,
                'match_reason' => "Already applied to {$posting->company} recently.",
            ]);

            return false;
        }

        $mailMessage = DB::transaction(function () use ($profile, $application, $posting, $account) {
            // Claim the application so an overlapping run can't send it twice.
            $claimed = JobApplication::whereKey($application->id)
                ->where('status', JobApplicationStatus::QUEUED)
                ->update(['status' => JobApplicationStatus::APPLIED, 'applied_at' => now()]);

            if (! $claimed) {
                return null;
            }

            $thread = $this->resolveThread($profile->user_id, $application->subject, null, null, $posting->apply_email);
            $bodyHtml = $this->sanitizer->sanitize($this->toHtml($application->cover_letter));

            $mailMessage = MailMessage::create([
                'mail_thread_id' => $thread->id,
                'user_id' => $profile->user_id,
                'direction' => 'outbound',
                'folder' => MailFolder::SENT->value,
                'from_address' => ['address' => $account->mailbox_address, 'name' => $profile->user->name],
                'to' => [['address' => $posting->apply_email, 'name' => $posting->company]],
                'cc' => [],
                'bcc' => [],
                'subject' => $application->subject,
                'body_html' => $bodyHtml,
                'body_text' => $application->cover_letter,
                'snippet' => MailMessage::snippet($application->cover_letter),
                'status' => MailStatus::QUEUED->value,
                'is_read' => true,
                'has_attachments' => true,
            ]);

            $this->attachCv($profile, $mailMessage);
            $this->refreshThreadAggregates($thread);

            $application->update([
                'mail_message_id' => $mailMessage->id,
                'mail_thread_id' => $thread->id,
            ]);

            return $mailMessage;
        });

        if (! $mailMessage) {
            return false;
        }

        SendMailMessageJob::dispatch($mailMessage->id);
        JobApplicationSentEvent::dispatch($application->fresh(['posting']));

        return true;
    }

    /**
     * A private copy of the CV for this message, so deleting or replacing
     * the CV later doesn't break the sent mail.
     */
    protected function attachCv(JobSeekerProfile $profile, MailMessage $mailMessage): void
    {
        $name = $profile->cv_original_name ?: 'CV.pdf';
        $path = "mail-attachments/{$mailMessage->id}/".Str::random(8).'-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.pdf';

        Storage::disk('local')->copy($profile->cv_path, $path);

        MailAttachment::create([
            'mail_message_id' => $mailMessage->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
        ]);
    }

    /**
     * Too many bounced applications switch job search off: the sending
     * domain's reputation matters more than any single application.
     */
    public function pauseIfBouncing(JobSeekerProfile $profile): bool
    {
        $bounced = $profile->user->jobApplications()
            ->where('status', JobApplicationStatus::FAILED)
            ->where('updated_at', '>=', now()->subDays(config('job_search.bounce_window_days')))
            ->count();

        if ($bounced < config('job_search.bounce_pause_threshold')) {
            return false;
        }

        self::stop($profile, "{$bounced} applications bounced in the last ".config('job_search.bounce_window_days').' days.');
        $profile->user->notify(new JobSearchPausedNotification($profile->paused_reason));

        return true;
    }

    /**
     * Switch job search off and hold back anything not yet sent.
     */
    public static function stop(JobSeekerProfile $profile, ?string $reason = null): void
    {
        $profile->update(['auto_apply' => false, 'paused_reason' => $reason]);

        $profile->user->jobApplications()
            ->where('status', JobApplicationStatus::QUEUED)
            ->update(['status' => JobApplicationStatus::PENDING_APPROVAL]);
    }

    public function withinSendingHours(): bool
    {
        $now = now(config('job_search.timezone'));
        [$from, $until] = config('job_search.send_hours');

        return $now->isWeekday() && $now->hour >= $from && $now->hour < $until;
    }

    protected function sentToday(JobSeekerProfile $profile): int
    {
        return $profile->user->jobApplications()
            ->where('applied_at', '>=', now(config('job_search.timezone'))->startOfDay())
            ->count();
    }

    protected function toHtml(string $text): string
    {
        return collect(preg_split("/\n\s*\n/", trim($text)))
            ->map(fn (string $paragraph) => '<p>'.str_replace("\n", '<br>', e(trim($paragraph))).'</p>')
            ->implode('');
    }
}
