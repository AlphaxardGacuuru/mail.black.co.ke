<?php

namespace App\Http\Services;

use App\Models\ChatMessageAttachment;
use App\Models\MailAttachment;
use App\Models\MailgunAccount;
use App\Models\Story;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deletes an account and everything in it: mail, chats, job search, CVs,
 * the files behind them, and its mailboxes on Mailgun. The database
 * removes most rows along with the user; this covers what it can't (files,
 * Mailgun, and tables linked without a foreign key).
 *
 * Kept: AI usage, under the deleted user's id, so monthly costs stay
 * right; and a job search address on Mailgun, so it's never given to
 * anyone else.
 */
class AccountDeletionService
{
    public function __construct(protected MailgunMailboxService $mailboxService)
    {
        //
    }

    public function delete(User $user): void
    {
        $this->removeMailboxes($user);

        // Listed before the rows that point to them are deleted.
        $files = $this->files($user);
        $directories = $this->directories($user);

        DB::transaction(function () use ($user) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            $user->tokens()->delete();
            $user->notifications()->delete();
            $user->pushSubscriptions()->delete();
            $user->twoFactorAuth()->delete();
            $user->delete();
        });

        $files->each(fn (array $file) => Storage::disk($file['disk'])->delete($file['path']));
        $directories->each(fn (string $directory) => Storage::disk('local')->deleteDirectory($directory));
    }

    /**
     * The user's own mailboxes are deleted on Mailgun. One that Mailgun
     * won't delete doesn't stop the account going; it's logged instead.
     */
    protected function removeMailboxes(User $user): void
    {
        $user->mailgunAccounts()
            ->get()
            ->reject(fn (MailgunAccount $account) => $account->isPlatform())
            ->each(function (MailgunAccount $account) {
                try {
                    $this->mailboxService->removeAccount($account);
                } catch (Throwable $exception) {
                    Log::warning('Account deletion: mailbox left on Mailgun', [
                        'mailbox' => $account->mailbox_address,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }

    /**
     * Files the user uploaded, received or was sent, on the public disk
     * unless the row says otherwise.
     *
     * @return Collection<int, array{disk: string, path: string}>
     */
    protected function files(User $user): Collection
    {
        $public = fn (?string $path) => ['disk' => 'public', 'path' => $path];

        // Only the user's own upload: the default picture is shared, and a
        // Google picture is a URL, not a file.
        return collect()
            ->when(
                $user->hasUploadedAvatar(),
                fn (Collection $files) => $files->push($public($user->getRawOriginal('avatar')))
            )
            ->merge(MailgunAccount::query()
                ->where('user_id', $user->id)
                ->pluck('avatar')
                ->map($public))
            ->merge(MailAttachment::query()
                ->whereHas('message', fn ($query) => $query->where('user_id', $user->id))
                ->get(['disk', 'path'])
                ->map(fn (MailAttachment $attachment) => ['disk' => $attachment->disk, 'path' => $attachment->path]))
            ->merge(ChatMessageAttachment::query()
                ->whereHas('message', fn ($query) => $query->where('sender_id', $user->id))
                ->get(['disk', 'path'])
                ->map(fn (ChatMessageAttachment $attachment) => ['disk' => $attachment->disk, 'path' => $attachment->path]))
            ->merge(SupportTicket::query()
                ->where('user_id', $user->id)
                ->pluck('attachments')
                ->flatten()
                ->map($public))
            ->merge(Story::query()
                ->where('user_id', $user->id)
                ->pluck('media')
                ->flatten()
                ->map($public))
            ->filter(fn (array $file) => is_string($file['path'])
                && $file['path'] !== ''
                && ! Str::startsWith($file['path'], ['http://', 'https://']))
            ->values();
    }

    /**
     * Private folders: the CV, its revamp and tailored copies, and each
     * application form's screenshots and uploads.
     *
     * @return Collection<int, string>
     */
    protected function directories(User $user): Collection
    {
        return $user->jobApplications()
            ->pluck('id')
            ->map(fn (string $id) => "application-forms/{$id}")
            ->push("job-search/cv/{$user->id}");
    }
}
