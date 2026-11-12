<?php

namespace App\Http\Services;

use App\Agents\ReplySuggestionAgent;
use App\Models\AiUsage;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailReplySuggestionService extends Service
{
    /**
     * How many of the thread's latest messages (up to the one being replied
     * to) are sent to the model.
     */
    private const MESSAGES_IN_CONTEXT = 5;

    /**
     * Longest slice of a single message's text that is sent.
     */
    private const MAX_CHARS_PER_MESSAGE = 2000;

    public function __construct(protected AiUsageService $aiUsageService)
    {
        parent::__construct();
    }

    /**
     * Draft a reply to one of the user's messages. The AI is asked at most
     * once per message: the first suggestion is kept and returned again on
     * every later request, whichever mode it asks for.
     *
     * @return array{status: int, message: string, data: array{text: string, html: string}|null, cached?: bool}
     */
    public function suggest(User $user, string $messageId, string $mode): array
    {
        $parent = MailMessage::query()
            ->where('user_id', $user->id)
            ->findOrFail($messageId);

        $cacheKey = "mail-reply-suggestion:{$user->id}:{$parent->id}";

        if ($text = Cache::get($cacheKey)) {
            return $this->response($text, cached: true);
        }

        try {
            $response = ReplySuggestionAgent::make()
                ->prompt(
                    $this->prompt($user, $parent, $mode),
                    model: config('services.mail_ai.reply_model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Reply suggestion failed', [
                'message_id' => $parent->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 503,
                'message' => "Suggested replies aren't available right now. Please try again later.",
                'data' => null,
            ];
        }

        $this->aiUsageService->record($user->id, AiUsage::FEATURE_REPLY_SUGGESTION, $response, $parent);

        $text = str($response->text)
            ->trim()
            ->toString();

        if ($text === '') {
            return [
                'status' => 503,
                'message' => "Couldn't suggest a reply for this message.",
                'data' => null,
            ];
        }

        Cache::forever($cacheKey, $text);

        return $this->response($text, cached: false);
    }

    /**
     * The prompt: who the owner is, then the latest messages of the thread.
     */
    private function prompt(User $user, MailMessage $parent, string $mode): string
    {
        $mailbox = $user->activeMailgunAccount?->mailbox_address ?? $user->email;

        $transcript = $this->threadMessages($parent)
            ->map(fn (MailMessage $message) => $this->describe($message))
            ->implode("\n\n---\n\n");

        $audience = $mode === 'reply-all'
            ? 'everyone on the last message'
            : 'the sender of the last message';

        return <<<PROMPT
        Mailbox owner: {$user->name} <{$mailbox}>
        Write the owner's reply to {$audience}.

        Thread (oldest first):

        {$transcript}
        PROMPT;
    }

    /**
     * The message being replied to and the few before it in its thread.
     *
     * @return Collection<int, MailMessage>
     */
    private function threadMessages(MailMessage $parent): Collection
    {
        $sentAt = $parent->received_at ?? $parent->sent_at ?? $parent->created_at;

        return MailMessage::query()
            ->where('user_id', $parent->user_id)
            ->where('mail_thread_id', $parent->mail_thread_id)
            ->whereNotIn('folder', ['draft', 'trash'])
            ->where('created_at', '<=', $parent->created_at)
            ->orderByDesc('created_at')
            ->limit(self::MESSAGES_IN_CONTEXT)
            ->get()
            ->when(
                fn (Collection $messages) => ! $messages->contains('id', $parent->id),
                fn (Collection $messages) => $messages->prepend($parent),
            )
            ->sortBy(fn (MailMessage $message) => $message->created_at)
            ->values();
    }

    /**
     * One message as plain text, without earlier quoted replies.
     */
    private function describe(MailMessage $message): string
    {
        $from = $message->from_address ?? [];
        $sender = trim(($from['name'] ?? '').' <'.($from['address'] ?? 'unknown').'>');
        $date = ($message->received_at ?? $message->sent_at ?? $message->created_at)?->toDayDateTimeString();
        $direction = $message->direction === 'outbound' ? ' (sent by the owner)' : '';

        return "From: {$sender}{$direction}\nDate: {$date}\nSubject: {$message->subject}\n\n"
            .$this->bodyWithoutQuotes($message);
    }

    /**
     * The message's own text: quoted history ("> ..." lines and everything
     * after an "On ... wrote:" line) is dropped, and the rest capped.
     */
    private function bodyWithoutQuotes(MailMessage $message): string
    {
        $text = $message->body_text ?: html_entity_decode(strip_tags((string) $message->body_html));

        return collect(preg_split('/\R/', $text))
            ->takeUntil(fn (string $line) => preg_match('/^\s*On .+wrote:\s*$/i', $line))
            ->reject(fn (string $line) => str_starts_with(ltrim($line), '>'))
            ->map(fn (string $line) => rtrim($line))
            ->pipe(fn ($lines) => str($lines->implode("\n")))
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->limit(self::MAX_CHARS_PER_MESSAGE, ' [...]')
            ->toString();
    }

    /**
     * @return array{status: int, message: string, data: array{text: string, html: string}, cached: bool}
     */
    private function response(string $text, bool $cached): array
    {
        return [
            'status' => 200,
            'message' => 'Suggested reply ready',
            'data' => [
                'text' => $text,
                'html' => self::toHtml($text),
            ],
            'cached' => $cached,
        ];
    }

    /**
     * Plain text paragraphs as safe HTML for the rich text editor.
     */
    public static function toHtml(string $text): string
    {
        return collect(preg_split("/\n\s*\n/", $text))
            ->map(fn (string $paragraph) => '<p>'.str_replace("\n", '<br>', e(trim($paragraph))).'</p>')
            ->implode('');
    }
}
