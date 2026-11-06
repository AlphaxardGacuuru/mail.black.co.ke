<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeenStory extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'story_id',
        'user_id',
        'seen_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'seen_at' => 'datetime',
    ];

    /*
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
