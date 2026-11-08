<?php

namespace App\Http\Resources;

use App\Services\Jobs\ApplicationForms\ApplicationFormService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class JobApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'matchScore' => $this->match_score,
            'matchReason' => $this->match_reason,
            'subject' => $this->subject,
            'coverLetter' => $this->cover_letter,
            'mailThreadId' => $this->mail_thread_id,
            'appliedAt' => $this->applied_at,
            'repliedAt' => $this->replied_at,
            'createdAt' => $this->created_at,
            'form' => $this->form_status ? $this->form() : null,
            // The CV tailored to this job, if one was asked for.
            'cv' => $this->cv_status ? [
                'status' => $this->cv_status->value,
                'url' => $this->hasTailoredCv()
                    ? URL::temporarySignedRoute('job-applications.cv', now()->addHours(2), [
                        'id' => $this->id,
                        'v' => $this->updated_at?->timestamp,
                    ])
                    : null,
            ] : null,
            'posting' => [
                'title' => $this->posting?->title,
                'company' => $this->posting?->company,
                'location' => $this->posting?->location,
                'url' => $this->posting?->url,
                'applyEmail' => $this->posting?->apply_email,
                'applyInstructions' => $this->posting?->apply_instructions,
                'source' => $this->posting?->source,
                'sourceName' => $this->posting?->sourceName(),
                'postedAt' => $this->posting?->posted_at,
                // Once known: opening the job works it out (see
                // JobApplicationController::show).
                'salary' => $this->posting?->salary,
            ],
        ];
    }

    /**
     * The employer's form, its questions with the suggested answers, and
     * the screenshots taken of it.
     *
     * @return array<string, mixed>
     */
    protected function form(): array
    {
        $fields = $this->form['fields'] ?? [];
        $service = app(ApplicationFormService::class);

        return [
            'status' => $this->form_status->value,
            'error' => $this->form_error,
            'url' => $this->form['url'] ?? null,
            'captcha' => $this->form['captcha'] ?? null,
            'captchaSolved' => (bool) config('job_search.application_forms.captcha.key'),
            'minutesLeft' => ApplicationFormService::minutesLeft($fields),
            'preparedAt' => $this->form_prepared_at,
            'submittedAt' => $this->form_submitted_at,
            'fields' => collect($fields)
                ->map(fn (array $field) => [
                    'key' => $field['key'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'required' => (bool) $field['required'],
                    'options' => $field['options'] ?? [],
                    'group' => $field['group'] ?? null,
                    'answer' => $field['answer'],
                    'effort' => $field['effort'],
                    'note' => $field['note'] ?: null,
                    'edited' => $field['edited'] ?? false,
                ])
                ->all(),
            'screenshots' => collect(['form', 'filled', 'confirmation'])
                ->mapWithKeys(fn (string $name) => [$name => $service->hasScreenshot($this->resource, $name)
                    ? URL::temporarySignedRoute('job-application-forms.screenshot', now()->addHours(2), [
                        'id' => $this->id,
                        'name' => $name,
                        // A new screenshot gets a new link, past the cache.
                        'v' => ($this->form_submitted_at ?? $this->form_prepared_at)?->timestamp,
                    ])
                    : null])
                ->all(),
        ];
    }
}
