<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job board read for everyone. Boards are added (and switched on) by an
 * admin with `job-boards:add`, after checking the board's terms allow it.
 */
class JobBoard extends Model
{
    use HasUuids;

    public const READER_STRUCTURED = 'structured';

    public const READER_MYJOBMAG = 'myjobmag';

    public const READER_AI = 'ai';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'last_listed_at' => 'datetime',
    ];

    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function isJobLink(string $url): bool
    {
        return preg_match($this->link_pattern, $url) === 1;
    }
}
