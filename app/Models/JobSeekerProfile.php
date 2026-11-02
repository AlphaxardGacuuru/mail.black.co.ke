<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerProfile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['cv_path'];

    protected $casts = [
        'auto_apply' => 'boolean',
        'consented_at' => 'datetime',
        'profile' => 'array',
        'preferences' => 'array',
        'parsed_at' => 'datetime',
        'last_discovery_at' => 'datetime',
        'last_alert_at' => 'datetime',
        'daily_limit' => 'integer',
        'min_match_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mailgunAccount(): BelongsTo
    {
        return $this->belongsTo(MailgunAccount::class);
    }

    /**
     * The mailbox applications are sent from: the chosen one, else the
     * user's active one.
     */
    public function sendingAccount(): ?MailgunAccount
    {
        return $this->mailgunAccount ?? $this->user?->activeMailgunAccount;
    }

    /**
     * Whether everything needed to apply on the user's behalf is in place.
     */
    public function isReady(): bool
    {
        return $this->auto_apply
            && $this->consented_at !== null
            && $this->profile !== null
            && $this->sendingAccount()?->mailbox_address !== null;
    }

    /**
     * Whether the user's own cover letter goes out unchanged, rather than
     * being tailored to each job by the AI.
     */
    public function sendsCoverLetterAsWritten(): bool
    {
        return $this->cover_letter_mode === 'as_is' && trim((string) $this->cover_letter) !== '';
    }

    public function isAutopilot(): bool
    {
        return $this->mode === 'autopilot';
    }

    /**
     * @return array<int, string>
     */
    public function preference(string $key): array
    {
        return array_values(array_filter((array) ($this->preferences[$key] ?? [])));
    }
}
