<?php

namespace App\Http\Services;

use App\Enums\MailFolder;
use App\Enums\MailStatus;
use App\Http\Services\Concerns\ResolvesMailThread;
use App\Jobs\SendMailMessageJob;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MailMessageService extends Service
{
    use ResolvesMailThread;

    public function __construct(protected MailSanitizerService $sanitizer)
    {
        parent::__construct();
    }

    /**
     * Compose and queue a brand new message.
     */
    public function store(Request $request)
    {
        $user = User::findOrFail($this->id);

        $this->ensureMailboxConfigured($user);

        $to = $this->normalizeAddresses($request->input('to', []));
        $cc = $this->normalizeAddresses($request->input('cc', []));
        $bcc = $this->normalizeAddresses($request->input('bcc', []));

        [$saved, $mailMessage] = $this->createOutboundMessage($user, [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $request->input('subject'),
            'bodyHtml' => $request->input('bodyHtml'),
            'inReplyTo' => null,
            'references' => null,
            'participantEmail' => $to[0]['address'] ?? null,
        ], $request->input('temporaryUploadIds', []));

        return [$saved, 'Message Queued for Sending', $mailMessage];
    }

    /**
     * Reply, reply-all, or forward to an existing message.
     */
    public function respond(Request $request, string $messageId, string $mode)
    {
        $user = User::findOrFail($this->id);
        $this->ensureMailboxConfigured($user);

        $parent = MailMessage::where('user_id', $this->id)->findOrFail($messageId);

        $to = match ($mode) {
            'forward' => $this->normalizeAddresses($request->input('to', [])),
            'reply' => [$parent->from_address],
            'reply-all' => array_values(array_filter(array_merge(
                [$parent->from_address],
                $parent->to ?? [],
            ), fn($address) => ($address['address'] ?? null) && $address['address'] !== $user->mailbox_address)),
            default => [],
        };

        $cc = $mode === 'reply-all' ? $this->normalizeAddresses($parent->cc ?? []) : $this->normalizeAddresses($request->input('cc', []));

        $subjectPrefix = $mode === 'forward' ? 'Fwd: ' : 'Re: ';
        $subject = preg_match('/^(re|fwd?|fw)\s*:/i', (string) $parent->subject)
            ? $parent->subject
            : $subjectPrefix . $parent->subject;

        $participantEmail = $mode === 'forward'
            ? ($to[0]['address'] ?? null)
            : ($parent->from_address['address'] ?? null);

        [$saved, $mailMessage] = $this->createOutboundMessage($user, [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $this->normalizeAddresses($request->input('bcc', [])),
            'subject' => $subject,
            'bodyHtml' => $request->input('bodyHtml'),
            'inReplyTo' => $mode !== 'forward' ? $parent->message_id : null,
            'references' => $mode !== 'forward'
                ? trim(($parent->references ?? '') . ' ' . ($parent->message_id ?? ''))
                : null,
            'participantEmail' => $participantEmail,
            'threadId' => $mode !== 'forward' ? $parent->mail_thread_id : null,
        ], $request->input('temporaryUploadIds', []));

        return [$saved, 'Message Queued for Sending', $mailMessage];
    }

    protected function ensureMailboxConfigured(User $user): void
    {
        if (! $user->mailbox_address) {
            throw ValidationException::withMessages([
                'mailbox_address' => ['Set up your mailbox address in Settings before sending mail.'],
            ]);
        }
    }

    protected function normalizeAddresses(array $addresses): array
    {
        return collect($addresses)
            ->map(function ($address) {
                if (is_string($address)) {
                    return ['address' => $address, 'name' => null];
                }

                return [
                    'address' => $address['address'] ?? null,
                    'name' => $address['name'] ?? null,
                ];
            })
            ->filter(fn($address) => filled($address['address']))
            ->values()
            ->all();
    }

    protected function createOutboundMessage(User $user, array $fields, array $temporaryUploadIds): array
    {
        $bodyHtml = $this->sanitizer->sanitize($fields['bodyHtml'] ?? null);
        $bodyText = trim(strip_tags((string) $bodyHtml));

        $thread = isset($fields['threadId']) && $fields['threadId']
            ? MailThread::find($fields['threadId'])
            : null;

        if (! $thread) {
            $thread = $this->resolveThread(
                $user->id,
                $fields['subject'],
                $fields['inReplyTo'] ?? null,
                $fields['references'] ?? null,
                $fields['participantEmail'] ?? null,
            );
        }

        $mailMessage = new MailMessage;
        $mailMessage->mail_thread_id = $thread->id;
        $mailMessage->user_id = $user->id;
        $mailMessage->direction = 'outbound';
        $mailMessage->folder = MailFolder::SENT->value;
        $mailMessage->from_address = [
            'address' => $user->mailbox_address,
            'name' => $user->name
        ];
        $mailMessage->to = $fields['to'] ?? [];
        $mailMessage->cc = $fields['cc'] ?? [];
        $mailMessage->bcc = $fields['bcc'] ?? [];
        $mailMessage->subject = $fields['subject'];
        $mailMessage->body_html = $bodyHtml;
        $mailMessage->body_text = $bodyText;
        $mailMessage->snippet = Str::limit($bodyText, 160);
        $mailMessage->in_reply_to = $fields['inReplyTo'] ?? null;
        $mailMessage->references = $fields['references'] ?? null;
        $mailMessage->status = MailStatus::QUEUED->value;
        $mailMessage->is_read = true;
        $mailMessage->has_attachments = ! empty($temporaryUploadIds);

        $saved = $mailMessage->save();

        if (! $saved) {
            return [$saved, $mailMessage];
        }

        $this->attachTemporaryUploads($mailMessage, $temporaryUploadIds);

        $this->refreshThreadAggregates($thread);

        return [$saved, $mailMessage];
    }

    protected function attachTemporaryUploads(MailMessage $mailMessage, array $temporaryUploadIds): void
    {
        $ids = collect($temporaryUploadIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $temporaryUploads = TemporaryUpload::whereIn('id', $ids)->get();

        foreach ($temporaryUploads as $temporaryUpload) {
            $disk = $temporaryUpload->disk ?: 'public';
            $finalPath = "mail-attachments/{$mailMessage->id}/" . basename($temporaryUpload->path);

            Storage::disk($disk)->move($temporaryUpload->path, $finalPath);

            $mailAttachment = new MailAttachment;
            $mailAttachment->mail_message_id = $mailMessage->id;
            $mailAttachment->disk = $disk;
            $mailAttachment->path = $finalPath;
            $mailAttachment->original_name = $temporaryUpload->original_name;
            $mailAttachment->mime_type = $temporaryUpload->mime_type;
            $mailAttachment->size = $temporaryUpload->size;
            $mailAttachment->save();
        }

        TemporaryUpload::whereIn('id', $temporaryUploads->pluck('id'))->delete();
    }
}
