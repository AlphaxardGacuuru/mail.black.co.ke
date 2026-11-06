<?php

namespace App\Http\Services;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ChatConversationService extends Service
{
    public function index(bool $archived = false): array
    {
        $this->touchLastSeen();

        $conversations = ChatConversation::forUser($this->id)
            ->when(
                $archived,
                fn($query) => $query->archivedBy($this->id),
                fn($query) => $query->notArchivedBy($this->id)
            )
            ->notDeletedBy($this->id)
            ->with([
                'participants',
                'messages' => fn($query) => $query->latest()->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return [true, $conversations->count() . ' Conversations Retrieved', $conversations];
    }

    public function startWith(string $otherUserId): array
    {
        if ($otherUserId === $this->id) {
            return [false, 'You Cannot Start A Conversation With Yourself', null];
        }

        $pairKey = collect([$this->id, $otherUserId])->sort()->implode(':');

        $existing = ChatConversation::where('type', 'direct')
            ->where('pair_key', $pairKey)
            ->first();

        if ($existing) {
            // Re-initiating with someone you'd archived should surface the
            // conversation again — archiving is a plain on/off flag with no
            // history attached, so there's nothing to protect by leaving it
            // archived. A delete cutoff (deleted_at) is left untouched here:
            // it marks "history before this point is gone for me", and
            // resuming the chat shouldn't resurrect that history — only a
            // message landing after the cutoff does (see scopeNotDeletedBy /
            // show()'s message filter).
            $existing->participants()->updateExistingPivot($this->id, [
                'archived_at' => null,
            ]);

            return [true, 'Conversation Already Exists', $existing->load('participants')];
        }

        try {
            $conversation = DB::transaction(function () use ($otherUserId, $pairKey) {
                $conversation = ChatConversation::create([
                    'type' => 'direct',
                    'pair_key' => $pairKey,
                ]);
                $conversation->participants()->attach([$this->id, $otherUserId]);

                return $conversation;
            });
        } catch (QueryException) {
            // Another request won the race and created this pair between our
            // lookup and our insert — the unique index on pair_key is what
            // actually guarantees only one conversation per pair exists;
            // just fetch whichever row it created.
            $conversation = ChatConversation::where('type', 'direct')
                ->where('pair_key', $pairKey)
                ->firstOrFail();
        }

        return [true, 'Conversation Started', $conversation->load('participants')];
    }

    public function show(string $id, int $page = 1): array
    {
        $this->touchLastSeen();

        $conversation = ChatConversation::forUser($this->id)->with('participants')->findOrFail($id);

        // "Read by recipient" is a property of each message relative to its
        // own sender, not to whoever is currently viewing the conversation —
        // in a back-and-forth thread the viewer is the recipient for half
        // the messages and the sender for the other half.
        $lastReadAtByUserId = $conversation->participants->mapWithKeys(
            fn($participant) => [$participant->id => $participant->pivot?->last_read_at]
        );

        // If I deleted this conversation, everything up to that point is
        // gone for me specifically — the other participant's view is
        // unaffected. A message landing after my cutoff is what brings the
        // conversation back for me, and it reads as the start of my history.
        $myDeletedAt = $conversation->participants->firstWhere('id', $this->id)?->pivot?->deleted_at;

        $messages = $conversation->messages()
            ->with(['sender', 'attachments', 'replyTo.attachments', 'stars'])
            ->when($myDeletedAt, fn($query, $deletedAt) => $query->where('created_at', '>', $deletedAt))
            ->orderByDesc('created_at')
            ->paginate(30, ['*'], 'page', $page);

        $messages->getCollection()->each(function ($message) use ($conversation, $lastReadAtByUserId) {
            $recipient = $conversation->participants->firstWhere('id', '!=', $message->sender_id);
            $recipientLastReadAt = $recipient ? $lastReadAtByUserId->get($recipient->id) : null;

            $message->is_read_by_recipient = $recipientLastReadAt !== null
                && $recipientLastReadAt->gte($message->created_at);

            $message->is_starred_by_viewer = $message
                ->stars
                ->contains('user_id', $this->id);
        });

        return [true, 'Conversation Retrieved', $conversation, $messages];
    }

    public function markRead(string $id): array
    {
        $conversation = ChatConversation::forUser($this->id)->findOrFail($id);

        $conversation->participants()->updateExistingPivot($this->id, [
            'last_read_at' => $this->preciseNow(),
        ]);

        return [true, 'Conversation Marked Read', $conversation];
    }

    public function toggleArchive(string $id): array
    {
        $conversation = ChatConversation::forUser($this->id)->findOrFail($id);

        $myPivot = $conversation->participants()->where('users.id', $this->id)->first()?->pivot;
        $isArchived = $myPivot?->archived_at !== null;

        $conversation->participants()->updateExistingPivot($this->id, [
            'archived_at' => $isArchived ? null : $this->preciseNow(),
        ]);

        return [true, $isArchived ? 'Conversation Unarchived' : 'Conversation Archived', ! $isArchived];
    }

    public function hideForMe(string $id): array
    {
        $conversation = ChatConversation::forUser($this->id)->findOrFail($id);

        // Sets my visibility cutoff to now: every message up to this point
        // stops showing up for me (see show()'s message filter), and the
        // conversation drops out of my index() until a message lands after
        // this timestamp (see scopeNotDeletedBy). The other participant and
        // the underlying messages are untouched.
        $conversation->participants()->updateExistingPivot($this->id, [
            'deleted_at' => $this->preciseNow(),
        ]);

        return [true, 'Conversation Removed'];
    }

    // updateExistingPivot() (and any other raw query-builder write) goes
    // through Laravel's binding layer, not Eloquent's per-model date
    // casting — a plain now() there gets truncated to second precision by
    // the query grammar's default date format before it reaches MySQL, even
    // though the column itself now stores microseconds. Passing an
    // already-formatted string sidesteps that truncation.
    protected function preciseNow(): string
    {
        return now()->format('Y-m-d H:i:s.u');
    }

    protected function touchLastSeen(): void
    {
        User::whereKey($this->id)
            ->where(function ($query) {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinute());
            })
            ->update(['last_seen_at' => now()]);
    }
}
