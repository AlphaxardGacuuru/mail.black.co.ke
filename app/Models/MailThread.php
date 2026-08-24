<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailThread extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'has_unread' => 'boolean',
        'is_starred' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class)->orderBy('created_at');
    }

    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFolder(Builder $query, string $folder): Builder
    {
        return $query->whereHas('messages', function (Builder $q) use ($folder) {
            $q->where('folder', $folder);
        });
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('has_unread', true);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }
}
