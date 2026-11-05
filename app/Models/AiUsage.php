<?php

namespace App\Models;

use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The tokens and cost of one AI request, so spend can be totalled per user.
 * Costs are stored in micro-dollars (millionths of a US dollar) because a single
 * request usually costs a small fraction of a cent. Requests no single user
 * asked for (shared careers page searches) have no user and count as "System".
 */
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, HasUuids;

    /**
     * A suggested reply on the compose form.
     */
    public const FEATURE_REPLY_SUGGESTION = 'reply_suggestion';

    /**
     * Reading an uploaded CV into a job seeker profile.
     */
    public const FEATURE_CV_PARSE = 'cv_parse';

    /**
     * Scoring a batch of job postings against a job seeker's profile.
     */
    public const FEATURE_JOB_MATCH = 'job_match';

    /**
     * Writing the subject and cover letter of a job application.
     */
    public const FEATURE_JOB_APPLICATION_DRAFT = 'job_application_draft';

    /**
     * Listing the positions on a company's careers page.
     */
    public const FEATURE_CAREER_PAGE_READ = 'career_page_read';

    /**
     * Reading a job page on a board that publishes no structured job data.
     * Shared by everyone, so it has no user.
     */
    public const FEATURE_JOB_PAGE_READ = 'job_page_read';

    /**
     * Listing the jobs in a job alert email the user signed up for on
     * another site.
     */
    public const FEATURE_JOB_ALERT_READ = 'job_alert_read';

    /**
     * Finding employers' careers pages with Google Search.
     */
    public const FEATURE_CAREER_PAGE_SEARCH = 'career_page_search';

    /**
     * Reading a job post the user shared (text, link or images).
     */
    public const FEATURE_SHARED_POST_READ = 'shared_post_read';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'feature',
        'provider',
        'model',
        'attempt',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'reasoning_tokens',
        'cost_micros',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cost_micros' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
