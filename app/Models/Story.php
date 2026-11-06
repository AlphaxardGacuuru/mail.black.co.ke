<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    use HasUuids, Prunable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'text',
        'media',
        'career_field',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'media' => 'array',
    ];

    /**
     * Accessors.
     */
    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value),
        );
    }

    /*
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seen(): HasMany
    {
        return $this->hasMany(SeenStory::class);
    }

    /*
     * Custom functions
     */

    /**
     * Stories expire 24 hours after they're posted (see `model:prune` in
     * routes/console.php).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDay());
    }

    /**
     * Delete the story's files before an expired story is pruned.
     */
    protected function pruning(): void
    {
        $this->deleteMedia();
    }

    /**
     * Delete every file of every media item (images and videos).
     */
    public function deleteMedia(): void
    {
        foreach ($this->media ?? [] as $media) {
            foreach ((array) $media as $path) {
                if (is_string($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
    }

    /**
     * Whether the given user has seen this story.
     */
    public function hasSeen(?string $userId): bool
    {
        return $this->seen->where('user_id', $userId)->isNotEmpty();
    }
}
