<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MailgunAccount extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['mailgun_smtp_password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mailgunDomain(): BelongsTo
    {
        return $this->belongsTo(MailgunDomain::class);
    }

    /**
     * A job search address on the app's own domain: it only sends job
     * applications and replies to them, and is never removed or reused.
     */
    public function isPlatform(): bool
    {
        return (bool) $this->mailgunDomain?->isPlatform();
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value && ! preg_match('/^https?:\/\//', $value) ? '/storage/'.$value : $value,
        );
    }
}
