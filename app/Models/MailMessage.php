<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailMessage extends Model
{
    use HasFactory, HasUuids;

    // Matches the `snippet` column's length.
    public const SNIPPET_LENGTH = 160;

    protected $guarded = [];

    protected $casts = [
        'from_address' => 'array',
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'reply_to' => 'array',
        'headers' => 'array',
        'folder_history' => 'array',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'has_attachments' => 'boolean',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (MailMessage $message) {
            $participants = collect([$message->from_address['address'] ?? null])
                ->merge(collect($message->to ?? [])->pluck('address'))
                ->merge(collect($message->cc ?? [])->pluck('address'))
                ->filter()
                ->implode(' ');

            $bodyExcerpt = mb_substr(trim(strip_tags((string) ($message->body_text ?? $message->body_html))), 0, 2000);

            $message->search_index = trim(($message->subject ?? '') . ' ' . $bodyExcerpt . ' ' . $participants);
        });
    }

    /**
     * A preview of the body that always fits the column: Str::limit appends
     * "..." after the limit, so the limit leaves room for it.
     */
    public static function snippet(?string $text): string
    {
        return Str::of((string) $text)
            ->trim()
            ->limit(self::SNIPPET_LENGTH - 3)
            ->toString();
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'mail_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class);
    }

    public function mailgunEvents(): HasMany
    {
        return $this->hasMany(MailgunEvent::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(MailLabel::class, 'mail_label_message');
    }

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFolder(Builder $query, string $folder): Builder
    {
        return $query->where('folder', $folder);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    /**
     * The owner's mailbox this message was sent to: the first To (then Cc)
     * address that matches one of their accounts, the same way the inbox
     * decides which account a message belongs to.
     */
    public function receivingAccount(): ?MailgunAccount
    {
        $addresses = collect([...($this->to ?? []), ...($this->cc ?? [])])
            ->pluck('address')
            ->filter()
            ->map(fn (string $address) => mb_strtolower($address))
            ->values();

        $accounts = MailgunAccount::query()
            ->where('user_id', $this->user_id)
            ->whereIn('mailbox_address', $addresses)
            ->get();

        return $addresses
            ->map(fn (string $address) => $accounts->firstWhere('mailbox_address', $address))
            ->first(fn (?MailgunAccount $account) => $account !== null);
    }
}
