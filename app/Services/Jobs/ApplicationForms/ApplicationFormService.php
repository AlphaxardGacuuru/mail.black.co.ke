<?php

namespace App\Services\Jobs\ApplicationForms;

use App\Agents\ApplicationFormAgent;
use App\Enums\ApplicationFormStatus;
use App\Enums\JobApplicationStatus;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Models\JobBoard;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Services\AiUsageService;
use App\Services\Jobs\MyJobMagSource;
use App\Services\Jobs\SafePageFetcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applying on an employer's own form. prepare() reads the form and
 * suggests an answer to each question, marked with how much the user has
 * to do: "filled" (from their profile), "check" (written by the AI),
 * "needs_you" (only they know it) or "optional". submit() fills the form
 * in with the answers they approved.
 */
class ApplicationFormService
{
    public const EFFORT_FILLED = 'filled';

    public const EFFORT_CHECK = 'check';

    public const EFFORT_NEEDS_YOU = 'needs_you';

    public const EFFORT_OPTIONAL = 'optional';

    // The CV is the only upload the app can give a form.
    public const FILE_CV = 'cv';

    protected const BLOCKERS = [
        'sign_in' => 'This site asks you to sign in before applying, so apply on the site.',
        'bot_wall' => 'This site turns away automated browsers, so apply on the site.',
        'no_form' => 'No application form was found on the page. Apply on the site.',
    ];

    public function __construct(
        protected BrowserService $browser,
        protected SafePageFetcher $fetcher,
        protected MyJobMagSource $myJobMag,
        protected AiUsageService $aiUsageService,
    ) {}

    public function prepare(JobApplication $application): void
    {
        $profile = $application->user->jobSeekerProfile;

        try {
            Storage::disk('local')->makeDirectory($this->directory($application));

            $url = $this->formUrl($application->posting);
            $result = $this->browser->inspect($url, $this->screenshotPath($application, 'form'));
        } catch (Throwable $exception) {
            Log::warning('Application form could not be read', ['application_id' => $application->id, 'error' => $exception->getMessage()]);

            $this->fail($application, ApplicationFormStatus::FAILED, "The application form couldn't be opened. Apply on the site.");

            return;
        }

        if (isset($result['error'])) {
            $this->fail($application, ApplicationFormStatus::FAILED, Str::limit($result['error'], 900));

            return;
        }

        if ($result['blocker'] ?? null) {
            $this->fail($application, ApplicationFormStatus::BLOCKED, self::BLOCKERS[$result['blocker']] ?? self::BLOCKERS['no_form']);

            return;
        }

        $application->update([
            'form_status' => ApplicationFormStatus::READY,
            'form' => [
                'url' => $result['url'],
                'adapter' => $result['adapter'] ?? null,
                'captcha' => $result['captcha'] ?? null,
                'fields' => $this->suggestAnswers($application, $profile, $result['fields']),
            ],
            'form_error' => null,
            'form_prepared_at' => now(),
        ]);
    }

    /**
     * Saves the user's answers, and remembers the ones they typed for the
     * same questions on later forms.
     *
     * @param  array<string, ?string>  $answers  by question key
     */
    public function saveAnswers(JobApplication $application, array $answers): void
    {
        $limit = (int) config('job_search.application_forms.max_answer_chars');

        $fields = collect($application->form['fields'] ?? [])
            ->map(fn (array $field) => array_key_exists($field['key'], $answers) && $field['type'] !== 'file'
                ? [
                    ...$field,
                    'answer' => Str::limit(trim((string) $answers[$field['key']]), $limit, ''),
                    'edited' => ($field['edited'] ?? false) || trim((string) $answers[$field['key']]) !== (string) $field['answer'],
                ]
                : $field);

        $application->update(['form' => [...$application->form, 'fields' => $fields->all()]]);

        $this->remember($application->user->jobSeekerProfile, $fields);
    }

    /**
     * Required questions still without an answer.
     *
     * @return Collection<int, string>
     */
    public function unanswered(JobApplication $application): Collection
    {
        return collect($application->form['fields'] ?? [])
            ->filter(fn (array $field) => $field['required'] && trim((string) $field['answer']) === '')
            ->reject(fn (array $field) => $field['type'] === 'checkbox' && isset($field['group']))
            ->map(fn (array $field) => $field['label'] ?: 'An unlabelled question')
            ->values();
    }

    public function submit(JobApplication $application): void
    {
        $profile = $application->user->jobSeekerProfile;
        $fields = collect($application->form['fields'] ?? []);

        try {
            $result = $this->browser->submit(
                $application->form['url'],
                $fields
                    ->map(fn (array $field) => collect($field)
                        ->only(['key', 'label', 'type', 'required'])
                        ->all())
                    ->all(),
                $fields
                    ->mapWithKeys(fn (array $field) => [$field['key'] => (string) $field['answer']])
                    ->all(),
                $this->files($application, $profile),
                [
                    'filled' => $this->screenshotPath($application, 'filled'),
                    'confirmation' => $this->screenshotPath($application, 'confirmation'),
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Application form could not be submitted', ['application_id' => $application->id, 'error' => $exception->getMessage()]);

            $result = ['error' => "The application form couldn't be opened. Try again, or apply on the site."];
        }

        if (! ($result['submitted'] ?? false)) {
            $this->fail($application, ApplicationFormStatus::SUBMIT_FAILED, Str::limit($result['error'] ?? 'The form was not submitted.', 900));

            return;
        }

        $application->update([
            'status' => JobApplicationStatus::APPLIED,
            'applied_at' => now(),
            'form_status' => ApplicationFormStatus::SUBMITTED,
            'form_error' => null,
            'form_submitted_at' => now(),
        ]);
    }

    /**
     * Rough minutes the user needs to finish the form: reading what the AI
     * wrote, and answering what only they know.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    public static function minutesLeft(array $fields): int
    {
        $minutes = collect($fields)
            ->reject(fn (array $field) => $field['edited'] ?? false)
            ->sum(fn (array $field) => match ($field['effort']) {
                self::EFFORT_CHECK => $field['type'] === 'textarea' ? 1.5 : 0.25,
                self::EFFORT_NEEDS_YOU => trim((string) $field['answer']) !== ''
                    ? 0
                    : ($field['type'] === 'textarea' ? 4 : 0.75),
                default => 0,
            });

        return (int) ceil($minutes);
    }

    /**
     * The screenshots the browser took: the empty form, the form filled in,
     * and the page after submitting.
     */
    public function screenshotPath(JobApplication $application, string $name): string
    {
        return Storage::disk('local')->path("{$this->directory($application)}/{$name}.png");
    }

    public function hasScreenshot(JobApplication $application, string $name): bool
    {
        return Storage::disk('local')->exists("{$this->directory($application)}/{$name}.png");
    }

    /**
     * Where the form is. A MyJobMag advert links to it through
     * /apply-now/{id}; postings read before that link was kept get it from
     * their page again.
     */
    protected function formUrl(JobPosting $posting): string
    {
        if (! $posting->apply_url && $posting->board?->reader === JobBoard::READER_MYJOBMAG) {
            $posting->update([
                'apply_url' => $this->myJobMag->parseJobPage($this->fetcher->fetch($posting->url))['apply_url'],
            ]);
        }

        $url = $posting->apply_url ?: $posting->url;

        $this->fetcher->assertPublicUrl((string) $url);

        return $url;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields  as read from the form
     * @return array<int, array<string, mixed>>
     */
    protected function suggestAnswers(JobApplication $application, JobSeekerProfile $profile, array $fields): array
    {
        $fields = collect($fields)->map(fn (array $field) => [
            ...$field,
            'label' => Str::limit((string) $field['label'], 500, ''),
            'options' => array_slice((array) ($field['options'] ?? []), 0, 300),
            'answer' => '',
            'effort' => null,
            'note' => '',
        ]);

        $known = $fields->map(fn (array $field) => $this->knownAnswer($application, $profile, $field));
        $asked = $fields->filter(fn (array $field, int $index) => $known[$index] === null && $field['type'] !== 'file');
        $suggested = $asked->isEmpty() ? collect() : $this->askAi($application, $profile, $asked->values());

        return $fields
            ->map(function (array $field, int $index) use ($known, $suggested) {
                $answer = $known[$index] ?? $suggested->get($field['key']) ?? ['answer' => '', 'source' => 'unknown', 'note' => ''];
                $empty = trim($answer['answer']) === '';

                return [
                    ...$field,
                    'answer' => $answer['answer'],
                    'note' => $answer['note'],
                    'effort' => match (true) {
                        ! $empty && $answer['source'] === 'drafted' => self::EFFORT_CHECK,
                        ! $empty => self::EFFORT_FILLED,
                        (bool) $field['required'] => self::EFFORT_NEEDS_YOU,
                        default => self::EFFORT_OPTIONAL,
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Answers that need no AI: uploads, name and email, and the user's
     * own answer to the same question on an earlier form.
     *
     * @param  array<string, mixed>  $field
     * @return array{answer: string, source: string, note: string}|null
     */
    protected function knownAnswer(JobApplication $application, JobSeekerProfile $profile, array $field): ?array
    {
        $label = Str::lower((string) $field['label']);
        $name = Str::of($profile->profile['full_name'] ?? $application->user->name)
            ->squish()
            ->explode(' ');
        $known = fn (string $answer, string $note = '') => ['answer' => $answer, 'source' => 'known', 'note' => $note];

        if ($field['type'] === 'file') {
            return preg_match('/cover|letter|motivation/', $label)
                ? ['answer' => '', 'source' => 'unknown', 'note' => 'Cover letter files can only be added on the site.']
                : $known(self::FILE_CV, 'Your CV');
        }

        $remembered = $profile->form_answers[$this->questionKey($label)]['answer'] ?? null;

        return match (true) {
            (bool) preg_match('/^(first|given) ?name/', $label) => $known($name->first()),
            (bool) preg_match('/^(last|sur|family) ?name/', $label) => $known($name->count() > 1 ? $name->last() : ''),
            (bool) preg_match('/^(full |your )?name$/', $label) => $known($name->implode(' ')),
            $field['type'] === 'email' || (bool) preg_match('/e-?mail/', $label) => $known(
                $profile->sendingAccount()?->mailbox_address ?? $application->user->email,
                'Replies come to your Black Mail inbox.',
            ),
            $remembered !== null => $known($remembered, 'Your answer on an earlier form'),
            default => null,
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $fields
     * @return Collection<string, array{answer: string, source: string, note: string}>
     */
    protected function askAi(JobApplication $application, JobSeekerProfile $profile, Collection $fields): Collection
    {
        $posting = $application->posting;

        try {
            $response = ApplicationFormAgent::make()
                ->prompt(
                    collect([
                        'Candidate CV:',
                        json_encode($profile->profile ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        'Preferred locations: '.(implode(', ', $profile->preference('locations')) ?: 'any'),
                        "Job:\n".collect([
                            "Title: {$posting->title}",
                            $posting->company ? "Company: {$posting->company}" : null,
                            $posting->location ? "Location: {$posting->location}" : null,
                            'Description: '.Str::limit((string) $posting->description, 4000, ' [...]'),
                        ])->filter()->implode("\n"),
                        "Questions:\n".$fields
                            ->map(fn (array $field) => collect($field)
                                ->only(['key', 'label', 'type', 'required', 'options', 'group', 'maxLength'])
                                ->filter(fn ($value) => $value !== null && $value !== [])
                                ->all())
                            ->toJson(JSON_UNESCAPED_UNICODE),
                    ])->implode("\n\n"),
                    model: config('job_search.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Application form answers failed', ['application_id' => $application->id, 'error' => $exception->getMessage()]);

            return collect();
        }

        $this->aiUsageService->record($application->user_id, AiUsage::FEATURE_APPLICATION_FORM, $response, $application);

        $byKey = $fields->keyBy('key');

        return collect($response['answers'] ?? [])
            ->filter(fn ($answer) => is_array($answer) && $byKey->has($answer['key'] ?? null))
            ->mapWithKeys(function (array $answer) use ($byKey) {
                $field = $byKey[$answer['key']];
                $text = trim((string) ($answer['answer'] ?? ''));

                // A choice that isn't one of the form's choices can't be
                // picked, so it's left for the user.
                if ($field['options'] !== [] && $text !== '') {
                    $text = collect($field['options'])->first(fn (string $option) => Str::lower($option) === Str::lower($text)) ?? '';
                }

                return [$answer['key'] => [
                    'answer' => $text === '' ? '' : Str::limit($text, (int) config('job_search.application_forms.max_answer_chars'), ''),
                    'source' => $text === '' ? 'unknown' : (string) ($answer['source'] ?? 'drafted'),
                    'note' => Str::limit(trim((string) ($answer['note'] ?? '')), 200, ''),
                ]];
            });
    }

    /**
     * Keeps the user's short typed answers (phone number, notice period,
     * salary) by question, newest first.
     *
     * @param  Collection<int, array<string, mixed>>  $fields
     */
    protected function remember(JobSeekerProfile $profile, Collection $fields): void
    {
        $typed = $fields
            ->filter(fn (array $field) => ($field['edited'] ?? false) || $field['effort'] === self::EFFORT_NEEDS_YOU)
            ->filter(fn (array $field) => ! in_array($field['type'], ['file', 'textarea', 'checkbox'], true))
            ->filter(fn (array $field) => $field['label'] !== '' && trim((string) $field['answer']) !== '' && mb_strlen($field['answer']) <= 300)
            ->mapWithKeys(fn (array $field) => [$this->questionKey($field['label']) => [
                'label' => $field['label'],
                'answer' => $field['answer'],
            ]]);

        if ($typed->isEmpty()) {
            return;
        }

        $profile->update([
            'form_answers' => $typed
                ->union(collect($profile->form_answers ?? [])->except($typed->keys()))
                ->take((int) config('job_search.application_forms.remembered_answers'))
                ->all(),
        ]);
    }

    /**
     * The same question worded with different punctuation or case gives
     * the same key.
     */
    protected function questionKey(string $label): string
    {
        return Str::of($label)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->limit(150, '')
            ->toString();
    }

    /**
     * The CV, under its original name: the name the employer sees.
     *
     * @return array<string, string>
     */
    protected function files(JobApplication $application, ?JobSeekerProfile $profile): array
    {
        if (! $profile?->cv_path || ! Storage::disk('local')->exists($profile->cv_path)) {
            return [];
        }

        $name = Str::of($profile->cv_original_name ?: 'CV.pdf')
            ->basename()
            ->replaceMatches('/[^\w.\- ]+/u', '')
            ->whenEmpty(fn () => Str::of('CV.pdf'))
            ->toString();
        $path = "{$this->directory($application)}/upload/{$name}";

        Storage::disk('local')->delete($path);
        Storage::disk('local')->copy($profile->cv_path, $path);

        return [self::FILE_CV => Storage::disk('local')->path($path)];
    }

    protected function fail(JobApplication $application, ApplicationFormStatus $status, string $message): void
    {
        $application->update([
            'form_status' => $status,
            'form_error' => $message,
        ]);
    }

    protected function directory(JobApplication $application): string
    {
        return "application-forms/{$application->id}";
    }
}
