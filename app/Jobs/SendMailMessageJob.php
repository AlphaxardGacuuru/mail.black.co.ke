<?php

namespace App\Jobs;

use App\Enums\MailStatus;
use App\Mail\ComposedMail;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMailMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $mailMessageId)
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $mailMessage = MailMessage::with('attachments')->find($this->mailMessageId);

        if (! $mailMessage || $mailMessage->status === MailStatus::SENT->value) {
            return;
        }

        $to = collect($mailMessage->to ?? [])->pluck('address')->filter()->all();
        $cc = collect($mailMessage->cc ?? [])->pluck('address')->filter()->all();
        $bcc = collect($mailMessage->bcc ?? [])->pluck('address')->filter()->all();

        $account = $mailMessage->user?->activeMailgunAccount;
        $sentMessage = $this->mailer($mailMessage->user)
            ->to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->send(new ComposedMail($mailMessage, $account?->signature));

        $messageId = $sentMessage ? trim((string) $sentMessage->getMessageId(), '<>') : null;

        $mailMessage->update([
            'status' => MailStatus::SENT->value,
            'message_id' => $messageId ?: $mailMessage->message_id,
            'mailgun_message_id' => $messageId,
            'sent_at' => now(),
        ]);
    }

    /**
     * Resolve the mailer that should send this message: the owning user's own
     * Mailgun account if they've configured one, otherwise the system default.
     */
    protected function mailer(?User $user): Mailer
    {
        $account = $user?->activeMailgunAccount;

        if ($account && filled($account->mailgun_domain) && filled($account->mailgun_api_key)) {
            $domain = $account->mailgun_domain;
            $secret = $account->mailgun_api_key;
            $endpoint = $account->mailgun_endpoint;
        } elseif (! $user?->hasMailgunCredentials()) {
            return Mail::mailer(config('mail.default'));
        } else {
            $domain = $user->mailgun_domain;
            $secret = $user->mailgun_api_key;
            $endpoint = $user->mailgun_endpoint;
        }

        $mailerName = "mailgun-user-{$user->id}-account-" . ($account?->id ?? 'legacy');

        Mail::purge($mailerName);

        config(["mail.mailers.{$mailerName}" => [
            'transport' => 'mailgun',
            'domain' => $domain,
            'secret' => $secret,
            'endpoint' => $endpoint ?: 'api.mailgun.net',
            'scheme' => 'https',
        ]]);

        return Mail::mailer($mailerName);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send mail message', [
            'mail_message_id' => $this->mailMessageId,
            'error' => $exception->getMessage(),
        ]);

        MailMessage::where('id', $this->mailMessageId)->update([
            'status' => MailStatus::FAILED->value,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
