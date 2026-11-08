<?php

namespace App\Services\Cv;

use App\Agents\CvRevampAgent;
use App\Enums\CvRevampStatus;
use App\Jobs\RevampCvJob;
use App\Models\AiUsage;
use App\Models\CvRevamp;
use App\Models\JobSeekerProfile;
use App\Services\AiUsageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Throwable;

/**
 * Revamping the user's CV: the AI rewrites it section by section and asks
 * for the facts that would make it stronger; the user picks which version
 * of each section to keep, answers the questions (which revamps it again)
 * and chooses whether to send it to employers instead of their own.
 */
class CvRevampService
{
    public function __construct(
        protected AiUsageService $aiUsageService,
        protected CvRenderer $renderer,
    ) {}

    /**
     * Revamp the profile's CV, again if it has been already: with the
     * user's answers when they've given some, from scratch when the CV
     * itself has changed.
     */
    public function start(JobSeekerProfile $profile): CvRevamp
    {
        $revamp = CvRevamp::query()
            ->firstOrNew(['user_id' => $profile->user_id]);

        if ($revamp->cv_hash !== $profile->cv_hash) {
            $revamp->fill(['document' => null, 'questions' => null]);
        }

        $revamp->fill([
            'cv_hash' => $profile->cv_hash,
            'status' => CvRevampStatus::DRAFTING,
            'error' => null,
        ])->save();

        RevampCvJob::dispatch($revamp->id);

        return $revamp;
    }

    public function revamp(CvRevamp $revamp): void
    {
        $profile = $revamp->user->jobSeekerProfile;

        // A different CV was uploaded since this was asked for.
        if (! $profile?->cv_path || $profile->cv_hash !== $revamp->cv_hash) {
            $revamp->delete();

            return;
        }

        try {
            $response = CvRevampAgent::make()
                ->prompt(
                    $this->prompt($profile, $revamp),
                    attachments: [Document::fromStorage($profile->cv_path, 'local')],
                    model: config('job_search.cv_revamp.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('CV revamp failed', ['revamp_id' => $revamp->id, 'error' => $exception->getMessage()]);

            $revamp->update([
                'status' => CvRevampStatus::FAILED,
                'error' => "Your CV couldn't be revamped just now. Try again in a few minutes.",
            ]);

            return;
        }

        $this->aiUsageService->record($revamp->user_id, AiUsage::FEATURE_CV_REVAMP, $response, $revamp);

        $sections = collect($response['sections'] ?? [])
            ->map(fn (array $section) => [
                'heading' => Str::of((string) ($section['heading'] ?? ''))->squish()->limit(60, '')->toString(),
                'original' => $this->entry($section['original'] ?? []),
                'revamped' => $this->entry($section['revamped'] ?? []),
                'why' => Str::of((string) ($section['why'] ?? ''))->squish()->limit(300)->toString(),
            ]);

        // The AI's numbering, so questions can point at a section.
        $keys = $sections->keys()
            ->mapWithKeys(fn (int $index) => [$index + 1 => 's'.($index + 1)]);

        $kept = $sections
            ->map(fn (array $section, int $index) => [
                'key' => $keys[$index + 1],
                ...$section,
                'keep' => 'revamped',
            ])
            ->filter(fn (array $section) => $section['heading'] !== '' && $section['revamped'] !== null)
            ->values();

        if ($kept->isEmpty()) {
            $revamp->update([
                'status' => CvRevampStatus::FAILED,
                'error' => "Your CV couldn't be read. Check that it's a text PDF rather than a scan, and try again.",
            ]);

            return;
        }

        $revamp->update([
            'status' => CvRevampStatus::READY,
            'document' => [
                'name' => Str::of((string) ($response['name'] ?? ''))->squish()->limit(100, '')->toString(),
                'headline' => Str::of((string) ($response['headline'] ?? ''))->squish()->limit(160, '')->toString(),
                'contact' => collect($response['contact'] ?? [])
                    ->map(fn ($line) => Str::of((string) $line)->squish()->limit(120, '')->toString())
                    ->filter()
                    ->take(6)
                    ->values()
                    ->all(),
                'sections' => $kept->all(),
            ],
            'questions' => $this->questions($revamp, collect($response['questions'] ?? []), $keys, $kept)->all(),
        ]);
    }

    /**
     * Which version of each section to keep, edits to the rewrites, and
     * answers to the AI's questions.
     *
     * @param  array<string, array{keep?: string, text?: string|null, bullets?: array<int, string>|null}>  $sections
     * @param  array<string, string|null>  $answers
     */
    public function update(CvRevamp $revamp, array $sections, array $answers): CvRevamp
    {
        $document = $revamp->document;

        $document['sections'] = collect($document['sections'])
            ->map(function (array $section) use ($sections) {
                $change = $sections[$section['key']] ?? null;

                if ($change === null) {
                    return $section;
                }

                $edited = collect(['text', 'bullets'])
                    ->filter(fn (string $field) => array_key_exists($field, $change))
                    ->isNotEmpty();

                return [
                    ...$section,
                    'keep' => ($change['keep'] ?? $section['keep']) === 'original' && $section['original'] ? 'original' : 'revamped',
                    'revamped' => $edited
                        ? $this->entry([
                            ...$section['revamped'],
                            ...collect($change)->only(['text', 'bullets'])->all(),
                        ]) ?? $section['revamped']
                        : $section['revamped'],
                    'edited' => ($section['edited'] ?? false) || $edited,
                ];
            })
            ->all();

        $revamp->update([
            'document' => $document,
            'questions' => collect($revamp->questions ?? [])
                ->map(fn (array $question) => array_key_exists($question['key'], $answers)
                    ? [
                        ...$question,
                        'answer' => Str::of((string) $answers[$question['key']])
                            ->trim()
                            ->limit((int) config('job_search.cv_revamp.max_answer_chars'), '')
                            ->toString(),
                    ]
                    : $question)
                ->all(),
        ]);

        return $revamp;
    }

    /**
     * Send the revamped CV to employers from now on: drawn as it stands,
     * as a PDF to attach and a Word file to download.
     */
    public function use(CvRevamp $revamp): void
    {
        $cv = $revamp->chosen();
        $directory = "job-search/cv/{$revamp->user_id}/revamp";
        $name = Str::random(24);

        $revamp->deleteFiles();

        Storage::disk('local')->put("{$directory}/{$name}.pdf", $this->renderer->pdf($cv));
        Storage::disk('local')->put("{$directory}/{$name}.docx", $this->renderer->docx($cv));

        $revamp->update([
            'pdf_path' => "{$directory}/{$name}.pdf",
            'docx_path' => "{$directory}/{$name}.docx",
            'used_at' => now(),
        ]);
    }

    /**
     * Go back to sending the uploaded CV. CVs already tailored to jobs
     * from the revamp are kept: they were made for those jobs.
     */
    public function stopUsing(CvRevamp $revamp): void
    {
        $revamp->update(['used_at' => null]);
    }

    protected function prompt(JobSeekerProfile $profile, CvRevamp $revamp): string
    {
        $answered = collect($revamp->questions ?? [])
            ->filter(fn (array $question) => trim((string) $question['answer']) !== '');

        return collect([
            'Roles they are going for: '.collect($profile->profile['target_titles'] ?? [])
                ->merge($profile->preference('keywords'))
                ->unique()
                ->implode(', '),
            $answered->isNotEmpty()
                ? "Their answers to your earlier questions:\n".$answered
                    ->map(fn (array $question) => "- {$question['question']}\n  {$question['answer']}")
                    ->implode("\n")
                : null,
        ])
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Questions answered before are kept (already used in this rewrite);
     * new ones are added after them.
     *
     * @param  Collection<int, mixed>  $asked
     * @param  Collection<int, string>  $keys
     * @param  Collection<int, array<string, mixed>>  $sections
     * @return Collection<int, array{key: string, section: string|null, question: string, answer: string, used: bool}>
     */
    protected function questions(CvRevamp $revamp, Collection $asked, Collection $keys, Collection $sections): Collection
    {
        $answered = collect($revamp->questions ?? [])
            ->filter(fn (array $question) => trim((string) $question['answer']) !== '')
            ->map(fn (array $question) => [...$question, 'section' => null, 'used' => true]);

        return $answered
            ->concat($asked
                ->filter(fn ($question) => is_array($question) && trim((string) ($question['question'] ?? '')) !== '')
                ->take(6)
                ->map(fn (array $question) => [
                    'section' => $keys[(int) ($question['section'] ?? 0)] ?? null,
                    'question' => Str::of((string) $question['question'])->squish()->limit(300)->toString(),
                    'answer' => '',
                    'used' => false,
                ]))
            ->values()
            ->map(fn (array $question, int $index) => [
                ...$question,
                'key' => 'q'.($index + 1),
                // Only point at a section that's still in the CV.
                'section' => $sections->contains('key', $question['section']) ? $question['section'] : null,
            ]);
    }

    /**
     * A section's content, tidied; null when there's nothing in it.
     *
     * @param  array<string, mixed>  $entry
     * @return array{title: string, subtitle: string, period: string, text: string, bullets: array<int, string>}|null
     */
    protected function entry(array $entry): ?array
    {
        $clean = fn (string $field, int $limit) => Str::of((string) ($entry[$field] ?? ''))
            ->squish()
            ->limit($limit, '')
            ->toString();

        $tidy = [
            'title' => $clean('title', 160),
            'subtitle' => $clean('subtitle', 160),
            'period' => $clean('period', 60),
            'text' => Str::of((string) ($entry['text'] ?? ''))->trim()->limit(1500, '')->toString(),
            'bullets' => collect($entry['bullets'] ?? [])
                ->map(fn ($bullet) => Str::of((string) $bullet)->squish()->ltrim('•-* ')->limit(400, '')->toString())
                ->filter()
                ->take(12)
                ->values()
                ->all(),
        ];

        return collect($tidy)->filter()->isEmpty() ? null : $tidy;
    }
}
