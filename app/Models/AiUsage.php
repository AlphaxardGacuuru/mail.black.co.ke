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
 * request usually costs a small fraction of a cent. Google searches that
 * ground an answer and CAPTCHA solves are paid for too, so they're included. Requests no single user
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
     * An email drafted from the user's instruction on the compose form.
     */
    public const FEATURE_MAIL_DRAFT = 'mail_draft';

    /**
     * The user's own message rewritten on the compose form.
     */
    public const FEATURE_MAIL_REVAMP = 'mail_revamp';

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

    /**
     * Finding the day's news for a job seeker's story, with Google Search.
     */
    public const FEATURE_CAREER_NEWS = 'career_news';

    /**
     * Tagging a job seeker's CV with career fields, for their news.
     */
    public const FEATURE_CAREER_FIELDS = 'career_fields';

    /**
     * Suggesting answers to the questions on an employer's application
     * form.
     */
    public const FEATURE_APPLICATION_FORM = 'application_form';

    /**
     * Rewriting the user's CV (again each time they answer its questions).
     */
    public const FEATURE_CV_REVAMP = 'cv_revamp';

    /**
     * Tailoring the user's revamped CV to one job.
     */
    public const FEATURE_CV_TAILOR = 'cv_tailor';

    /**
     * A CAPTCHA solved by the solving service while submitting an
     * application form. Not an AI request: no tokens, just the service's
     * price.
     */
    public const FEATURE_CAPTCHA_SOLVE = 'captcha_solve';

    /**
     * Finding or estimating a job's pay, the first time anyone opens it.
     * Kept on the posting and shared by everyone, so it has no user.
     */
    public const FEATURE_JOB_SALARY = 'job_salary';

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
        'searches',
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
            'searches' => 'integer',
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
