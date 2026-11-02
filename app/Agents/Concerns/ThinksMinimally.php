<?php

namespace App\Agents\Concerns;

use Laravel\Ai\Enums\Lab;

/**
 * Gemini 3 models think before answering by default. Reading, scoring and
 * writing short texts doesn't need it, and thinking is billed as output
 * and slows the answer, so it's kept to a minimum.
 */
trait ThinksMinimally
{
    public function providerOptions(Lab|string $provider): array
    {
        return [
            'thinkingConfig' => ['thinkingLevel' => 'minimal'],
        ];
    }
}
