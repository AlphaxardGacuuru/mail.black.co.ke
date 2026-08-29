<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MailgunEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }
}
