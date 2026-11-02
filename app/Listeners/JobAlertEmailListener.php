<?php

namespace App\Listeners;

use App\Events\MailMessageReceivedEvent;
use App\Jobs\ReadJobAlertJob;
use App\Models\JobSeekerProfile;
use App\Services\Jobs\JobAlertReader;

/**
 * Job alerts the user signed up for on other sites (LinkedIn, Fuzu, ...)
 * arrive like any other mail; for users with job search on, the jobs in
 * them are added to their matches.
 */
class JobAlertEmailListener
{
    public function handle(MailMessageReceivedEvent $event): void
    {
        $message = $event->mailMessage;

        if (! JobAlertReader::alertSource($message)) {
            return;
        }

        $jobSearchOn = JobSeekerProfile::query()
            ->where('user_id', $message->user_id)
            ->where('auto_apply', true)
            ->exists();

        if ($jobSearchOn) {
            ReadJobAlertJob::dispatch($message->id);
        }
    }
}
