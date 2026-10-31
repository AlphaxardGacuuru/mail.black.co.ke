<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCareerPage extends Model
{
    use HasUuids;

    public const SOURCE_USER = 'user';

    public const SOURCE_SEARCH = 'search';

    protected $guarded = [];

    protected $casts = [
        'last_fetched_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * The site a link is on, without "www.", for telling sites apart.
     */
    public static function hostOf(string $url): string
    {
        return preg_replace('/^www\./', '', mb_strtolower((string) parse_url($url, PHP_URL_HOST)));
    }
}
