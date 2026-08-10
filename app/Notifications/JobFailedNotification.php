<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobFailedNotification extends Notification
{
    use Queueable;

    protected $jobName;

    protected $exception;

    protected $failedAt;

    protected $additionalData;

    /**
     * Create a new notification instance.
     */
    public function __construct($jobName, $exception, $additionalData = [])
    {
        $this->jobName = $jobName;
        $this->exception = $exception;
        $this->failedAt = now();
        $this->additionalData = $additionalData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->error()
            ->subject("🚨 Job Failed: {$this->jobName}")
            ->greeting("Job Failure Alert")
            ->line("A scheduled job has failed in your Black Property application.")
            ->line("**Job Name:** {$this->jobName}")
            ->line("**Failed At:** {$this->failedAt}")
            ->line("**Error Message:** {$this->exception->getMessage()}")
            ->line("**File:** {$this->exception->getFile()}")
            ->line("**Line:** {$this->exception->getLine()}")
            ->when(! empty($this->additionalData), function ($message) {
                $message->line("**Additional Data:**");
                foreach ($this->additionalData as $key => $value) {
                    $message->line("- **{$key}:** ".(is_array($value) ? json_encode($value) : $value));
                }
            })
            ->line("**Stack Trace:**")
            ->line("```")
            ->line($this->exception->getTraceAsString())
            ->line("```")
            ->line("Please check the application logs for more details.")
            ->action('View Telescope', url('/telescope'))
            ->line('This is an automated message from Black Property system.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'job_name' => $this->jobName,
            'error_message' => $this->exception->getMessage(),
            'failed_at' => $this->failedAt,
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
        ];
    }
}
