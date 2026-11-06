<?php

namespace App\Services\Stories;

use App\Agents\CareerFieldsAgent;
use App\Models\AiUsage;
use App\Models\JobSeekerProfile;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tags a job seeker's CV with career fields (config job_search.news.fields),
 * once per CV, so they see the news story for their line of work.
 */
class CareerFieldsTagger
{
    public function __construct(protected AiUsageService $aiUsageService) {}

    /**
     * @return array<int, string>
     */
    public function tag(JobSeekerProfile $profile): array
    {
        if ($profile->career_fields_at && $profile->career_fields_at->gte($profile->parsed_at)) {
            return $profile->career_fields ?? [];
        }

        $cv = $profile->profile ?? [];

        try {
            $response = CareerFieldsAgent::make()
                ->prompt(
                    collect([
                        'Headline: '.($cv['headline'] ?? ''),
                        'Roles: '.collect($cv['target_titles'] ?? [])->implode(', '),
                        'Skills: '.collect($cv['skills'] ?? [])->take(10)->implode(', '),
                    ])->implode("\n"),
                    model: config('job_search.news.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Career fields tagging failed', ['user_id' => $profile->user_id, 'error' => $exception->getMessage()]);

            return $profile->career_fields ?? [];
        }

        $this->aiUsageService->record($profile->user_id, AiUsage::FEATURE_CAREER_FIELDS, $response, $profile);

        $profile->update([
            'career_fields' => collect((array) ($response['fields'] ?? []))
                ->intersect(array_keys(config('job_search.news.fields')))
                ->unique()
                ->take((int) config('job_search.news.max_fields'))
                ->values()
                ->all(),
            'career_fields_at' => now(),
        ]);

        return $profile->career_fields;
    }
}
