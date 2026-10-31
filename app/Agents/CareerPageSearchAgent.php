<?php

namespace App\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Searches the web for employers' own careers pages for a set of roles and
 * places. Answers in plain lines rather than structured output, which
 * Gemini doesn't combine with Google Search on every model.
 */
#[Provider(Lab::Gemini)]
#[MaxTokens(1200)]
#[Temperature(0.2)]
#[Timeout(45)]
class CareerPageSearchAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You find employers' own careers pages using Google Search.

        You receive job roles and places. Search for companies and
        organisations in those places that hire for those roles, and find the
        page on each one's own website that lists its open jobs or vacancies.

        Rules:
        - Only the employer's own website (or its own page on a hiring
          system such as Workday, Greenhouse or Lever). Never job boards, job
          aggregators, recruitment agencies, news articles or social media.
        - Only links you found in search results. Never guess or construct a
          link.
        - Prefer the page that lists the vacancies over a home page.
        - Up to 15 employers, one per line, exactly like this:
          Company name | https://link-to-the-careers-page
        - No other text.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            (new WebSearch)->location(country: 'KE'),
        ];
    }
}
