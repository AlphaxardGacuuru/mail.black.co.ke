<?php

namespace App\Models;

use App\Enums\ApplicationFormStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\TailoredCvStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JobApplication extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => JobApplicationStatus::class,
        'form_status' => ApplicationFormStatus::class,
        'form' => 'array',
        'cv_status' => TailoredCvStatus::class,
        'cv' => 'array',
        'form_prepared_at' => 'datetime',
        'form_submitted_at' => 'datetime',
        'match_score' => 'integer',
        'applied_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    /**
     * The CV for this application: the one tailored to it when there is
     * one, else the profile's.
     *
     * @return array{path: string, name: string}|null
     */
    public function cvToSend(JobSeekerProfile $profile): ?array
    {
        $revamp = $profile->user?->cvRevamp;

        if ($this->hasTailoredCv() && $revamp) {
            return ['path' => $this->cv_path, 'name' => $revamp->fileName('pdf')];
        }

        return $profile->cvToSend();
    }

    public function hasTailoredCv(): bool
    {
        return $this->cv_status === TailoredCvStatus::READY
            && $this->cv_path !== null
            && Storage::disk('local')->exists($this->cv_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'mail_thread_id');
    }
}
