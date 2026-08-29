<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'from_address' => 'array',
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'reply_to' => 'array',
        'headers' => 'array',
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
}
