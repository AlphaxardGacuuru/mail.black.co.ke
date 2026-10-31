<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    use HasUuids;

    public const SOURCE_MYJOBMAG = 'myjobmag';

    public const SOURCE_COMPANY_PAGE = 'company_page';

    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'details_fetched_at' => 'datetime',
    ];

    public function careerPage(): BelongsTo
    {
        return $this->belongsTo(CompanyCareerPage::class, 'company_career_page_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Postings a user may be matched against: public boards plus their own
     * company pages, with full details, and recent enough.
     */
    public function scopeAvailableTo(Builder $query, string $userId): Builder
    {
        return $query
            ->where(fn (Builder $owner) => $owner->whereNull('user_id')->orWhere('user_id', $userId))
            ->where(fn (Builder $page) => $page
                ->whereNull('company_career_page_id')
                ->orWhereHas('careerPage', fn (Builder $careerPage) => $careerPage->whereNull('dismissed_at')))
            ->whereNotNull('details_fetched_at')
            ->where(fn (Builder $recent) => $recent
                ->whereNull('posted_at')
                ->orWhere('posted_at', '>=', now()->subDays(config('job_search.max_posting_age_days'))));
    }

    public static function fingerprint(string ...$parts): string
    {
        return hash('sha256', mb_strtolower(implode('|', array_map('trim', $parts))));
    }

    /**
     * A company name reduced to its comparable core, so "Acme Ltd" and
     * "ACME Limited" count as the same employer.
     */
    public static function normalizeCompany(?string $company): string
    {
        $name = mb_strtolower(trim((string) $company));
        $name = preg_replace('/\b(ltd|limited|plc|inc|llc|co|company|group|kenya)\b\.?/u', '', $name);

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    }
}
