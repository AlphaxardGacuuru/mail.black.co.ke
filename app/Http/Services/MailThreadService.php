<?php

namespace App\Http\Services;

use App\Enums\MailFolder;
use App\Http\Services\Concerns\ResolvesMailThread;
use App\Models\MailLabel;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MailThreadService extends Service
{
    use ResolvesMailThread;

    public function index(Request $request)
    {
        $folder = $request->input('folder', MailFolder::INBOX->value);
        $labelId = $request->input('label');
        $q = trim((string) $request->input('q', ''));

        $query = MailThread::query()
            ->ownedBy($this->id)
            ->whereHas('messages', fn ($messageQuery) => $this->scopeToAccount($messageQuery));

        if ($request->boolean('starred') || $folder === 'starred') {
            $query->starred();
        } elseif ($folder) {
            $query->folder($folder);
        }

        if ($labelId) {
            $query->whereHas('messages.labels', fn ($labelQuery) => $labelQuery->where('mail_labels.id', $labelId));
        }

        if ($q !== '') {
            $query->whereHas('messages', function ($messageQuery) use ($q) {
                $messageQuery->whereFullText('search_index', $q.'*', ['mode' => 'boolean'])
                    ->orWhere('subject', 'like', "%{$q}%");
            });
        }

        $threads = $query->orderByDesc('last_message_at')->paginate(20);

        return [true, $threads->total().' Threads Retrieved', $threads];
    }

    public function show(string $id)
    {
        $thread = MailThread::ownedBy($this->id)
            ->whereHas('messages', fn ($messageQuery) => $this->scopeToAccount($messageQuery))
            ->with(['messages.attachments', 'messages.labels'])
            ->findOrFail($id);

        $thread->messages()->where('is_read', false)->update(['is_read' => true]);
        $thread->refresh();
        $thread->load(['messages.attachments', 'messages.labels']);
        $this->refreshThreadAggregates($thread);

        return [true, 'Thread Retrieved Successfully', $thread];
    }

    public function star(string $id)
    {
        return $this->toggleStar($id, true);
    }

    public function unstar(string $id)
    {
        return $this->toggleStar($id, false);
    }

    protected function toggleStar(string $id, bool $starred)
    {
        $thread = MailThread::ownedBy($this->id)->findOrFail($id);
        $thread->messages()->update(['is_starred' => $starred]);
        $thread->is_starred = $starred;
        $thread->save();

        return [true, $starred ? 'Thread Starred' : 'Thread Unstarred', $thread];
    }

    public function archive(string $id)
    {
        return $this->moveFolder($id, MailFolder::ARCHIVE->value, 'Thread Archived');
    }

    public function unarchive(string $id)
    {
        return $this->restoreOriginalFolders($id, 'Thread Restored');
    }

    public function trash(string $id)
    {
        return $this->moveFolder($id, MailFolder::TRASH->value, 'Thread Moved to Trash');
    }

    public function restore(string $id)
    {
        return $this->restoreOriginalFolders($id, 'Thread Restored');
    }

    protected function restoreOriginalFolders(string $id, string $message)
    {
        $thread = MailThread::ownedBy($this->id)->with('messages')->findOrFail($id);

        $thread->messages()
            ->where('direction', 'outbound')
            ->update(['folder' => MailFolder::SENT->value]);
        $thread->messages()
            ->where('direction', 'inbound')
            ->update(['folder' => MailFolder::INBOX->value]);

        return [true, $message, $thread];
    }

    protected function moveFolder(string $id, string $folder, string $message)
    {
        $thread = MailThread::ownedBy($this->id)->findOrFail($id);
        $thread->messages()->update(['folder' => $folder]);

        return [true, $message, $thread];
    }

    public function markRead(string $id)
    {
        return $this->toggleRead($id, true);
    }

    protected function scopeToAccount($query): void
    {
        $account = User::find($this->id)?->activeMailgunAccount;

        if (! $account) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($messageQuery) use ($account) {
            $messageQuery
                ->where('direction', 'inbound')
                ->whereJsonContains('to', [['address' => $account->mailbox_address]])
                ->orWhere(function ($outboundQuery) use ($account) {
                    $outboundQuery
                        ->where('direction', 'outbound')
                        ->where('from_address->address', $account->mailbox_address);
                });
        });
    }

    public function markUnread(string $id)
    {
        return $this->toggleRead($id, false);
    }

    protected function toggleRead(string $id, bool $read)
    {
        $thread = MailThread::ownedBy($this->id)->findOrFail($id);
        $thread->messages()->update(['is_read' => $read]);
        $this->refreshThreadAggregates($thread);

        return [true, $read ? 'Marked as Read' : 'Marked as Unread', $thread];
    }

    public function destroy(string $id)
    {
        $thread = MailThread::ownedBy($this->id)->with('messages.attachments')->findOrFail($id);

        $notInTrash = $thread->messages->contains(fn ($message) => $message->folder !== MailFolder::TRASH->value);

        if ($notInTrash) {
            throw ValidationException::withMessages([
                'thread' => ['Move this thread to trash before deleting it permanently.'],
            ]);
        }

        foreach ($thread->messages as $message) {
            foreach ($message->attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
        }

        $deleted = $thread->delete();

        return [$deleted, 'Thread Permanently Deleted', null];
    }

    public function attachLabel(string $threadId, string $labelId)
    {
        $thread = MailThread::ownedBy($this->id)->findOrFail($threadId);
        $label = MailLabel::where('user_id', $this->id)->findOrFail($labelId);

        foreach ($thread->messages as $message) {
            $message->labels()->syncWithoutDetaching([$label->id]);
        }

        return [true, 'Label Applied', $thread];
    }

    public function detachLabel(string $threadId, string $labelId)
    {
        $thread = MailThread::ownedBy($this->id)->findOrFail($threadId);
        $label = MailLabel::where('user_id', $this->id)->findOrFail($labelId);

        foreach ($thread->messages as $message) {
            $message->labels()->detach($label->id);
        }

        return [true, 'Label Removed', $thread];
    }
}
