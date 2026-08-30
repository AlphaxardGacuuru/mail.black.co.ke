<?php

namespace App\Http\Services;

use App\Enums\MailFolder;
use App\Enums\MailStatus;
use App\Http\Services\Concerns\ResolvesMailThread;
use App\Models\MailAttachment;
use App\Models\MailgunAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MailInboundService
{
    use ResolvesMailThread;

    protected const ALLOWED_ATTACHMENT_MIME_PREFIXES = ['image/', 'text/', 'application/pdf', 'application/msword', 'application/vnd.'];

    protected const MAX_ATTACHMENT_SIZE = 26_214_400; // 25MB, matches Mailgun's own inbound cap

    public function __construct(protected MailSanitizerService $sanitizer) {}

    public function handleInboundMail(Request $request): array
    {
        $recipient = $this->normalizeRecipient($request->input('recipient', ''));

        Log::info('Mailgun inbound received', [
            'recipient' => $recipient,
            'sender' => $request->input('sender'),
            'subject' => $request->input('subject'),
            'message_id' => $request->input('Message-Id'),
            'attachment_count' => (int) $request->input('attachment-count', 0),
        ]);

        $user = MailgunAccount::where('mailbox_address', $recipient)->first()?->user;

        if (! $user) {
            Log::warning('Mailgun inbound: no matching mailbox for recipient', [
                'recipient' => $recipient
            ]);

            return [true, 'No Matching Mailbox, Dropped', null, false];
        }

        $messageId = $this->trimMessageId((string) $request->input('Message-Id', ''));

        if ($messageId !== '' && MailMessage::where('user_id', $user->id)->where('message_id', $messageId)->exists()) {
            return [true, 'Duplicate Delivery, Already Processed', null, false];
        }

        $fromHeader = (string) $request->input('from', $request->input('sender', ''));
        $fromAddress = $this->parseFromHeader($fromHeader, (string) $request->input('sender', ''));

        $subject = (string) $request->input('subject', '(no subject)');
        $inReplyTo = $this->trimMessageId((string) $request->input('In-Reply-To', ''));
        $references = (string) $request->input('References', '');

        $thread = $this->resolveThread(
            $user->id,
            $subject,
            $inReplyTo ?: null,
            $references ?: null,
            $fromAddress['address'] ?? null,
        );

        $bodyHtml = $this->sanitizer->sanitize(
            $request->input('stripped-html') ?: $request->input('body-html')
        );
        $bodyText = (string) ($request->input('stripped-text') ?: $request->input('body-plain', ''));

        $mailMessage = new MailMessage;
        $mailMessage->mail_thread_id = $thread->id;
        $mailMessage->user_id = $user->id;
        $mailMessage->direction = 'inbound';
        $mailMessage->folder = MailFolder::INBOX->value;
        $mailMessage->from_address = $fromAddress;
        $mailMessage->to = $this->parseAddressList((string) $request->input('To', $recipient));
        $mailMessage->cc = $this->parseAddressList((string) $request->input('Cc', ''));
        $mailMessage->subject = $subject;
        $mailMessage->body_html = $bodyHtml;
        $mailMessage->body_text = $bodyText;
        $mailMessage->snippet = Str::limit(trim(strip_tags($bodyText ?: (string) $bodyHtml)), 160);
        $mailMessage->message_id = $messageId ?: null;
        $mailMessage->in_reply_to = $inReplyTo ?: null;
        $mailMessage->references = $references ?: null;
        $mailMessage->status = MailStatus::RECEIVED->value;
        $mailMessage->is_read = false;
        $mailMessage->received_at = now();
        $saved = $mailMessage->save();

        if (! $saved) {
            return [$saved, 'Inbound Mail Could Not Be Stored', $mailMessage, false];
        }

        $attachmentCount = $this->storeAttachments($request, $mailMessage);

        $mailMessage->update(['has_attachments' => $attachmentCount > 0]);

        $this->refreshThreadAggregates($thread);

        $mailMessage->setRelation('user', $user);

        return [true, 'Inbound Mail Stored', $mailMessage, true];
    }

    protected function normalizeRecipient(string $recipient): string
    {
        $recipient = Str::of($recipient)->trim()->lower();

        if ($recipient->contains('+') && $recipient->contains('@')) {
            $local = $recipient->before('@')->before('+');
            $domain = $recipient->after('@');

            return "{$local}@{$domain}";
        }

        return $recipient->toString();
    }

    protected function parseFromHeader(string $from, string $fallbackAddress): array
    {
        if (preg_match('/^(.*)<(.+)>$/', trim($from), $matches)) {
            return [
                'address' => mb_strtolower(trim($matches[2])),
                'name' => trim($matches[1], " \t\"") ?: null,
            ];
        }

        return [
            'address' => mb_strtolower(trim($from ?: $fallbackAddress)),
            'name' => null,
        ];
    }

    protected function parseAddressList(string $list): array
    {
        $addresses = Str::of($list)->trim();

        if ($addresses->isEmpty()) {
            return [];
        }

        return $addresses
            ->explode(',')
            ->map(fn($entry) => $this->parseFromHeader(Str::of($entry)->trim()->toString(), ''))
            ->filter(fn($address) => filled($address['address']))
            ->values()
            ->all();
    }

    protected function storeAttachments(Request $request, MailMessage $mailMessage): int
    {
        $count = (int) $request->input('attachment-count', 0);

        if ($count < 1) {
            return 0;
        }

        $stored = collect(range(1, $count))
            ->map(fn(int $index) => $request->file("attachment-{$index}"))
            ->filter()
            ->filter(function ($file) use ($mailMessage) {
                if (! $file->isValid()) {
                    return false;
                }

                if ($file->getSize() > self::MAX_ATTACHMENT_SIZE) {
                    Log::warning('Mailgun inbound: attachment exceeded size cap, skipped', [
                        'mail_message_id' => $mailMessage->id,
                        'size' => $file->getSize(),
                    ]);

                    return false;
                }

                $mime = $file->getMimeType() ?? 'application/octet-stream';

                if (! $this->isAllowedMime($mime)) {
                    Log::warning('Mailgun inbound: attachment mime type not allowed, skipped', [
                        'mail_message_id' => $mailMessage->id,
                        'mime' => $mime,
                    ]);

                    return false;
                }

                return true;
            })
            ->each(function ($file) use ($mailMessage) {
                $originalName = $file->getClientOriginalName();
                $path = $file->storeAs("mail-attachments/{$mailMessage->id}", $originalName, 'public');

                $mailAttachment = new MailAttachment;
                $mailAttachment->mail_message_id = $mailMessage->id;
                $mailAttachment->disk = 'public';
                $mailAttachment->path = $path;
                $mailAttachment->original_name = $originalName;
                $mailAttachment->mime_type = $file->getMimeType() ?? 'application/octet-stream';
                $mailAttachment->size = $file->getSize();
                $mailAttachment->save();
            });

        return $stored->count();
    }

    protected function isAllowedMime(string $mime): bool
    {
        return collect(self::ALLOWED_ATTACHMENT_MIME_PREFIXES)
            ->contains(fn(string $prefix): bool => Str::startsWith($mime, $prefix));
    }
}
