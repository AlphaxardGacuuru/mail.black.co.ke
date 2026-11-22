<?php

namespace App\Models;

use Database\Factories\UserLoginFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in: who, how, and from which IP address and browser. Kept for
 * RETENTION_DAYS, while User::$last_active_at keeps the latest activity
 * for good.
 */
class UserLogin extends Model
{
    /** @use HasFactory<UserLoginFactory> */
    use HasFactory, HasUuids, Prunable;

    public const METHOD_GOOGLE = 'google';

    /**
     * Email and password, including when a two-factor code follows.
     */
    public const METHOD_PASSWORD = 'password';

    public const RETENTION_DAYS = 180;

    protected $fillable = [
        'user_id',
        'method',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
