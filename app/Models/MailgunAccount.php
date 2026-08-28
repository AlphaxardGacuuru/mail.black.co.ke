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

    protected $hidden = ['mailgun_api_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value && ! preg_match('/^https?:\/\//', $value) ? '/storage/'.$value : $value,
        );
    }
}
