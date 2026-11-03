<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class MailgunDomain extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['api_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(MailgunAccount::class);
    }

    /**
     * The app's own domain (services.mailgun), where job search addresses
     * are created. It belongs to no user, and its key follows the config.
     */
    public static function platform(): self
    {
        return static::query()
            ->updateOrCreate(
                ['user_id' => null, 'domain' => config('services.mailgun.domain')],
                ['api_key' => config('services.mailgun.secret'), 'endpoint' => config('services.mailgun.endpoint')]
            );
    }

    public function isPlatform(): bool
    {
        return $this->user_id === null;
    }
}
