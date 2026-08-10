<?php

namespace App\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class DashboardNarrationAgent implements Agent
{
    use Promptable;

    public function provider(): string
    {
        return Lab::Gemini->value;
    }

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are a concise property portfolio analyst.
        You receive live dashboard metrics for a property management system and return
        a short plain-English narrative insight (2–3 sentences) plus a list of up to
        three actionable highlights the property manager should pay attention to.
        Use specific numbers from the data. Do not mention percentages that are 0.
        Tone: professional, direct, helpful. No markdown, no headers.
        INSTRUCTIONS;
    }

    public function __construct()
    {
        //
    }
}
