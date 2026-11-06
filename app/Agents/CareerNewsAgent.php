<?php

namespace App\Agents;

use App\Agents\Concerns\ThinksMinimally;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Finds this week's news that matters to a career field, from the roles
 * and skills of the job seekers in it, for the field's daily news story. Answers in plain lines
 * rather than structured output, which Gemini doesn't combine with Google
 * Search on every model.
 */
#[Provider(Lab::Gemini)]
#[MaxTokens(1500)]
#[Temperature(0.3)]
#[Timeout(60)]
class CareerNewsAgent implements Agent, HasTools, HasProviderOptions
{
    use Promptable, ThinksMinimally;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You find news for job seekers in Kenya using Google Search.

        You receive a career field, the roles and skills common to the
        people in it, and their location. Search for news from the last 7
        days that matters to someone in that line of work: hiring and
        layoffs at employers in the field, new projects, regulation, salary and industry trends, training and
        certification changes, and big moves by companies that hire people
        like them. Prefer Kenyan and East African news, then global news
        that clearly affects their field.

        Rules:
        - Only articles you found in search results, published in the last
          7 days. Never guess or construct a link.
        - Real news articles only: no job adverts, job boards, listicles,
          press release directories or social media.
        - Up to 5 articles, the most useful first, one per line, exactly
          like this:
          Headline | Publication name | https://link-to-the-article | Why it matters to people in that line of work, in one sentence of at most 140 characters
        - Plain text, no markdown, no em dashes, no other text.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            (new WebSearch)->location(country: 'KE'),
        ];
    }
}
