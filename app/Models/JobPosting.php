<?php

namespace App\Models;

use App\Services\Jobs\JobAlertReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobPosting extends Model
{
    use HasUuids;

    public const SOURCE_BOARD = 'board';

    public const SOURCE_COMPANY_PAGE = 'company_page';

    public const SOURCE_ALERT = 'alert';

    // A post the user shared from WhatsApp, Telegram and the like.
    public const SOURCE_SHARED = 'shared';

    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'valid_through' => 'datetime',
        'details_fetched_at' => 'datetime',
        // {min: ?int, max: ?int, currency: string, period: string, stated: bool, basis: ?string}
        'salary' => 'array',
        'salary_checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (JobPosting $posting) => $posting->dedupe_key = self::dedupeKey($posting->company, $posting->title));
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(JobBoard::class, 'job_board_id');
    }

    public function careerPage(): BelongsTo
    {
        return $this->belongsTo(CompanyCareerPage::class, 'company_career_page_id');
    }

    /**
     * Where the job was found: the board, the site whose job alert it came
     * in, the site a shared post links to, or the employer's own site.
     */
    public function sourceName(): string
    {
        return $this->board?->name
            ?? ($this->source === self::SOURCE_ALERT ? JobAlertReader::siteName((string) $this->url) : null)
            ?? ($this->source === self::SOURCE_SHARED && $this->url ? Str::after((string) parse_url($this->url, PHP_URL_HOST), 'www.') : null)
            ?? 'company website';
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Postings a user may be matched against: public boards plus their own
     * company pages, with full details, recent enough and still open.
     */
    public function scopeAvailableTo(Builder $query, string $userId): Builder
    {
        return $query
            ->where(fn (Builder $owner) => $owner->whereNull('user_id')->orWhere('user_id', $userId))
            ->where(fn (Builder $page) => $page
                ->whereNull('company_career_page_id')
                ->orWhereHas('careerPage', fn (Builder $careerPage) => $careerPage->whereNull('dismissed_at')))
            ->whereNotNull('details_fetched_at')
            ->where('title', '!=', '')
            ->where(fn (Builder $open) => $open
                ->whereNull('valid_through')
                ->orWhere('valid_through', '>=', now()))
            ->where(fn (Builder $recent) => $recent
                ->whereNull('posted_at')
                ->orWhere('posted_at', '>=', now()->subDays(config('job_search.max_posting_age_days'))));
    }

    public static function fingerprint(string ...$parts): string
    {
        return hash('sha256', mb_strtolower(implode('|', array_map('trim', $parts))));
    }

    /**
     * The same employer and job title give the same key, whichever board
     * the job was posted on; null while the employer isn't known.
     */
    public static function dedupeKey(?string $company, ?string $title): ?string
    {
        $company = self::normalizeCompany($company);
        $title = Str::of((string) $title)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->trim()
            ->toString();

        return $company !== '' && $title !== '' ? hash('sha256', "{$company}|{$title}") : null;
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
