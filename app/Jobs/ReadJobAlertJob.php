<?php

namespace App\Jobs;

use App\Models\MailMessage;
use App\Services\Jobs\JobAlertReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadJobAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public string $mailMessageId) {}

    public function handle(JobAlertReader $reader): void
    {
        $message = MailMessage::query()
            ->with('user.jobSeekerProfile')
            ->find($this->mailMessageId);
        $profile = $message?->user?->jobSeekerProfile;

        if (! $profile?->auto_apply) {
            return;
        }

        try {
            $reader->read($profile, $message);
        } catch (Throwable $exception) {
            Log::warning('Job alert reading failed', ['message_id' => $message->id, 'error' => $exception->getMessage()]);
        }
    }
}
