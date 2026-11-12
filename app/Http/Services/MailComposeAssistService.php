<?php

namespace App\Http\Services;

use App\Agents\MailDraftAgent;
use App\Agents\MailRevampAgent;
use App\Models\AiUsage;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Throwable;

/**
 * AI help while writing on the compose form: a draft from a short
 * instruction, and rewrites of what the user has written.
 */
class MailComposeAssistService extends Service
{
    /**
     * How each rewrite style is put to the AI.
     */
    public const STYLES = [
        'polish' => 'Polish it: correct and clear, in the same tone and about the same length.',
        'formal' => 'Make it more formal and professional.',
        'friendly' => 'Make it warmer and friendlier, still professional.',
        'shorter' => 'Make it shorter and more direct, keeping everything that matters.',
    ];

    /**
     * Longest draft that is sent to be rewritten.
     */
    private const MAX_BODY_CHARS = 8000;

    public function __construct(protected AiUsageService $aiUsageService)
    {
        parent::__construct();
    }

    /**
     * A new email written from the user's instruction.
     *
     * @param  array<int, string>  $to
     * @return array{status: int, message: string, data: array{text: string, html: string}|null}
     */
    public function draft(User $user, string $instruction, ?string $subject, array $to): array
    {
        $recipients = collect($to)
            ->filter()
            ->implode(', ');

        $prompt = collect([
            "Mailbox owner: {$this->owner($user)}",
            $recipients !== '' ? "To: {$recipients}" : null,
            filled($subject) ? "Subject: {$subject}" : null,
            "What the email should say:\n{$instruction}",
        ])
            ->filter()
            ->implode("\n\n");

        return $this->ask(MailDraftAgent::make(), $prompt, $user, AiUsage::FEATURE_MAIL_DRAFT, 'Draft Ready', "Couldn't write a draft right now. Please try again later.");
    }

    /**
     * The user's message rewritten in one of the STYLES.
     *
     * @return array{status: int, message: string, data: array{text: string, html: string}|null}
     */
    public function revamp(User $user, string $bodyHtml, string $style, ?string $subject): array
    {
        // The editor's HTML as plain text, a line per paragraph or break.
        $body = str($bodyHtml)
            ->replaceMatches('#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', "\n")
            ->stripTags()
            ->pipe(fn ($text) => html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches("/[ \t]+\n/", "\n")
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->limit(self::MAX_BODY_CHARS, '')
            ->toString();

        $prompt = collect([
            "Mailbox owner: {$this->owner($user)}",
            filled($subject) ? "Subject: {$subject}" : null,
            'How to rewrite it: '.self::STYLES[$style],
            "Draft:\n\n{$body}",
        ])
            ->filter()
            ->implode("\n\n");

        return $this->ask(MailRevampAgent::make(), $prompt, $user, AiUsage::FEATURE_MAIL_REVAMP, 'Rewrite Ready', "Couldn't rewrite your message right now. Please try again later.");
    }

    private function owner(User $user): string
    {
        $mailbox = $user->activeMailgunAccount?->mailbox_address ?? $user->email;

        return "{$user->name} <{$mailbox}>";
    }

    /**
     * @return array{status: int, message: string, data: array{text: string, html: string}|null}
     */
    private function ask(Agent $agent, string $prompt, User $user, string $feature, string $success, string $failure): array
    {
        try {
            $response = $agent->prompt($prompt, model: config('services.mail_ai.reply_model'));
        } catch (Throwable $exception) {
            Log::warning('Compose assist failed', [
                'feature' => $feature,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return ['status' => 503, 'message' => $failure, 'data' => null];
        }

        $this->aiUsageService->record($user->id, $feature, $response);

        $text = str($response->text)
            ->trim()
            ->toString();

        return $text === ''
            ? ['status' => 503, 'message' => $failure, 'data' => null]
            : [
                'status' => 200,
                'message' => $success,
                'data' => [
                    'text' => $text,
                    'html' => MailReplySuggestionService::toHtml($text),
                ],
            ];
    }
}
