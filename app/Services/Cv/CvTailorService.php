<?php

namespace App\Services\Cv;

use App\Agents\CvTailorAgent;
use App\Enums\TailoredCvStatus;
use App\Jobs\TailorCvJob;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A copy of the user's revamped CV tailored to one job: its headline,
 * profile, bullets and skills put in this employer's terms. It's attached
 * to that application instead of the revamped CV. One AI request per
 * application, so it's off unless the user turns it on (or asks for it on
 * one application).
 */
class CvTailorService
{
    public function __construct(
        protected AiUsageService $aiUsageService,
        protected CvRenderer $renderer,
    ) {}

    public function request(JobApplication $application): void
    {
        $application->update(['cv_status' => TailoredCvStatus::TAILORING]);

        TailorCvJob::dispatch($application->id);
    }

    public function tailor(JobApplication $application): void
    {
        $revamp = $application->user->cvRevamp;
        $posting = $application->posting;

        if (! $revamp?->inUse() || ! $posting) {
            $application->update(['cv_status' => TailoredCvStatus::FAILED]);

            return;
        }

        $cv = $revamp->chosen();

        try {
            $response = CvTailorAgent::make()
                ->prompt(
                    collect([
                        "CV:\n".json_encode([
                            'headline' => $cv['headline'],
                            'sections' => $cv['sections'],
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        "Job posting:\n".collect([
                            "Title: {$posting->title}",
                            $posting->company ? "Company: {$posting->company}" : null,
                            'Description: '.Str::limit((string) $posting->description, 4000, ' [...]'),
                        ])
                            ->filter()
                            ->implode("\n"),
                    ])->implode("\n\n"),
                    model: config('job_search.cv_revamp.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('CV tailoring failed', ['application_id' => $application->id, 'error' => $exception->getMessage()]);

            $application->update(['cv_status' => TailoredCvStatus::FAILED]);

            return;
        }

        $this->aiUsageService->record($application->user_id, AiUsage::FEATURE_CV_TAILOR, $response, $application);

        $tailored = collect($response['sections'] ?? [])
            ->filter(fn ($section) => is_array($section) && isset($section['key']))
            ->keyBy('key');

        $cv = [
            ...$cv,
            'headline' => Str::of((string) ($response['headline'] ?? ''))->squish()->limit(160, '')->whenEmpty(fn () => Str::of($cv['headline']))->toString(),
            'sections' => collect($cv['sections'])
                ->map(fn (array $section) => $tailored->has($section['key'])
                    ? [...$section, 'entry' => $this->tailorEntry($section['entry'], $tailored[$section['key']])]
                    : $section)
                ->all(),
        ];

        $path = "job-search/cv/{$application->user_id}/applications/{$application->id}.pdf";

        Storage::disk('local')->put($path, $this->renderer->pdf($cv));

        $application->update([
            'cv_status' => TailoredCvStatus::READY,
            'cv' => $cv,
            'cv_path' => $path,
        ]);
    }

    /**
     * The tailored text and bullets; titles, organisations and periods stay
     * as they were. A section the AI emptied keeps its own content.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $tailored
     * @return array<string, mixed>
     */
    protected function tailorEntry(array $entry, array $tailored): array
    {
        $bullets = collect($tailored['bullets'] ?? [])
            ->map(fn ($bullet) => Str::of((string) $bullet)->squish()->ltrim('•-* ')->limit(400, '')->toString())
            ->filter()
            ->take(12)
            ->values();
        $text = Str::of((string) ($tailored['text'] ?? ''))->trim()->limit(1500, '')->toString();

        return [
            ...$entry,
            'text' => $entry['text'] !== '' && $text !== '' ? $text : $entry['text'],
            'bullets' => $entry['bullets'] && $bullets->isNotEmpty() ? $bullets->all() : $entry['bullets'],
        ];
    }
}
