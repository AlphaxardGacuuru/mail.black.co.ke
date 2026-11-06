<?php

namespace App\Agents;

use App\Agents\Concerns\ThinksMinimally;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Tags a job seeker's CV with the career fields it belongs to, from a fixed
 * list, so people in the same line of work share one daily news story.
 */
#[Provider(Lab::Gemini)]
#[MaxTokens(200)]
#[Temperature(0.1)]
#[Timeout(30)]
class CareerFieldsAgent implements Agent, HasStructuredOutput, HasProviderOptions
{
    use Promptable, ThinksMinimally;

    public function instructions(): string
    {
        $fields = collect(config('job_search.news.fields'))
            ->map(fn (string $label, string $key) => "- {$key}: {$label}")
            ->implode("\n");

        return <<<INSTRUCTIONS
        You sort job seekers into career fields, from what their CV says
        they do and the roles they want.

        Pick the one field that fits them best, and a second only when they
        clearly work in both. Use only these keys:

        {$fields}
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fields' => $schema->array()
                ->items($schema->string()->enum(array_keys(config('job_search.news.fields'))))
                ->max((int) config('job_search.news.max_fields'))
                ->required(),
        ];
    }
}
