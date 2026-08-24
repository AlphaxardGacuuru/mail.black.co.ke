<?php

namespace App\Http\Services\Concerns;

use App\Models\MailMessage;
use App\Models\MailThread;
use Illuminate\Support\Carbon;

trait ResolvesMailThread
{
    /**
     * Normalize a subject line for thread matching: strips repeated
     * Re:/Fwd:/Fw: prefixes, collapses whitespace, lowercases.
     */
    protected function normalizeSubject(?string $subject): string
    {
        $subject = trim((string) $subject);

        while (preg_match('/^(re|fwd?|fw)\s*:\s*/i', $subject)) {
            $subject = preg_replace('/^(re|fwd?|fw)\s*:\s*/i', '', $subject);
        }

        $subject = preg_replace('/\s+/', ' ', $subject ?? '');

        return mb_strtolower(trim($subject ?? ''));
    }

    protected function trimMessageId(string $id): string
    {
        return trim($id, "<> \t\n\r\0\x0B");
    }

    /**
     * @return array<int, string>
     */
    protected function extractMessageIdCandidates(?string $inReplyTo, ?string $references): array
    {
        $ids = [];

        if ($inReplyTo) {
            $ids[] = $this->trimMessageId($inReplyTo);
        }

        if ($references) {
            foreach (preg_split('/\s+/', trim($references)) as $reference) {
                if ($reference !== '') {
                    $ids[] = $this->trimMessageId($reference);
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Resolve (or create) the thread a message belongs to, for a given
     * mailbox owner. Matches by In-Reply-To/References header correlation
     * first, then falls back to normalized subject + participant overlap
     * within a bounded window, else starts a new thread.
     */
    protected function resolveThread(
        string $userId,
        ?string $subject,
        ?string $inReplyTo,
        ?string $references,
        ?string $participantEmail
    ): MailThread {
        $candidates = $this->extractMessageIdCandidates($inReplyTo, $references);

        if (! empty($candidates)) {
            $existing = MailMessage::where('user_id', $userId)
                ->whereIn('message_id', $candidates)
                ->first();

            if ($existing) {
                return $existing->thread;
            }
        }

        $normalized = $this->normalizeSubject($subject);

        if ($normalized !== '' && $participantEmail) {
            $thread = MailThread::where('user_id', $userId)
                ->where('normalized_subject', $normalized)
                ->where('last_message_at', '>=', Carbon::now()->subDays(90))
                ->whereHas('messages', function ($query) use ($participantEmail) {
                    $query->where('from_address->address', $participantEmail)
                        ->orWhereRaw('JSON_SEARCH(`to`, \'one\', ?) IS NOT NULL', [$participantEmail]);
                })
                ->orderByDesc('last_message_at')
                ->first();

            if ($thread) {
                return $thread;
            }
        }

        $thread = new MailThread;
        $thread->user_id = $userId;
        $thread->subject = $subject;
        $thread->normalized_subject = $normalized;
        $thread->save();

        return $thread;
    }

    /**
     * Recompute and persist a thread's denormalized aggregates.
     */
    protected function refreshThreadAggregates(MailThread $thread): void
    {
        $messages = $thread->messages()->get();

        $thread->message_count = $messages->count();
        $thread->last_message_at = $messages->max('created_at');
        $thread->has_unread = $messages->contains(fn ($message) => ! $message->is_read && $message->direction === 'inbound');
        $thread->is_starred = $messages->contains('is_starred', true);

        $latest = $messages->sortByDesc('created_at')->first();

        if ($latest) {
            $thread->subject = $latest->subject;
        }

        $thread->save();
    }
}
