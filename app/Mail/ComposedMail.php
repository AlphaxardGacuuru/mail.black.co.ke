<?php

namespace App\Mail;

use App\Models\MailMessage;
use App\Http\Services\MailSanitizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MailMessage $mailMessage,
        public ?string $signature = null,
        public ?string $mailFromName = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = $this->mailMessage->from_address ?? [];

        return new Envelope(
            from: new Address(
                $from['address'] ?? config('mail.from.address'),
                $this->mailFromName ?: ($from['name'] ?? null),
            ),
            subject: $this->mailMessage->subject ?? '(no subject)',
        );
    }

    public function content(): Content
    {
        $body = $this->mailMessage->body_html ?? nl2br(e($this->mailMessage->body_text ?? ''));
        $signature = $this->signature
            ? '<br><br>'.app(MailSanitizerService::class)->sanitize($this->signature)
            : '';

        return new Content(htmlString: $body . $signature);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->mailMessage->attachments->map(
            fn($attachment) => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_name ?? basename($attachment->path))
                ->withMime($attachment->mime_type ?? 'application/octet-stream')
        )->all();
    }

    public function headers(): Headers
    {
        $references = array_filter(preg_split('/\s+/', trim((string) $this->mailMessage->references)) ?: []);

        return new Headers(
            references: $references,
            text: array_filter([
                'In-Reply-To' => $this->mailMessage->in_reply_to ? "<{$this->mailMessage->in_reply_to}>" : null,
            ]),
        );
    }
}
