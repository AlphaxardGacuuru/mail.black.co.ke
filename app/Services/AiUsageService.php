<?php

namespace App\Services;

use App\Models\AiUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\TextResponse;

class AiUsageService
{
    /**
     * Record the tokens an AI response used and what they cost. A null user
     * is a system-wide request that no single user asked for.
     */
    public function record(?string $userId, string $feature, TextResponse $response, ?Model $subject = null, ?int $attempt = null): AiUsage
    {
        $usage = $response->usage;
        $model = $this->modelName($response->meta->model);

        return AiUsage::create([
            'user_id' => $userId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'feature' => $feature,
            'provider' => $response->meta->provider,
            'model' => $model,
            'attempt' => $attempt,
            'input_tokens' => $usage->promptTokens,
            'cached_input_tokens' => $usage->cacheReadInputTokens,
            'output_tokens' => $usage->completionTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'cost_micros' => $this->costInMicros(
                $model,
                $usage->promptTokens,
                $usage->cacheReadInputTokens,
                $usage->completionTokens + $usage->reasoningTokens,
            ),
        ]);
    }

    /**
     * What the tokens cost in micro-dollars. A price per million tokens times a
     * token count is already in micro-dollars, so no further conversion is needed.
     * Reasoning ("thinking") tokens are billed as output.
     */
    public function costInMicros(?string $model, int $inputTokens, int $cachedInputTokens, int $outputTokens): int
    {
        // Looked up by plain key: model names contain dots, which config()
        // would read as nesting ("gemini-3.1-flash-lite").
        $price = $model ? (config('ai.pricing') ?? [])[$model] ?? null : null;

        if (! is_array($price)) {
            Log::warning('AI usage: no price is configured for this model, so its cost was recorded as zero.', ['model' => $model]);

            return 0;
        }

        return (int) round(
            $inputTokens * $price['input']
            + $cachedInputTokens * ($price['cached_input'] ?? $price['input'])
            + $outputTokens * $price['output']
        );
    }

    /**
     * Each user's AI requests, tokens and cost for the month, most expensive
     * first. Requests without a user are totalled together as "System".
     *
     * @return Collection<int, array{userId: string|null, name: string, email: string|null, requests: int, replySuggestions: int, inputTokens: int, outputTokens: int, costMicros: int}>
     */
    public function monthlyTotals(CarbonImmutable $month): Collection
    {
        return AiUsage::query()
            ->leftJoin('users', 'users.id', '=', 'ai_usages.user_id')
            ->whereBetween('ai_usages.created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('ai_usages.user_id', 'users.name', 'users.email')
            ->orderByDesc('cost_micros')
            ->get([
                'ai_usages.user_id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as requests'),
                DB::raw("SUM(CASE WHEN feature = '".AiUsage::FEATURE_REPLY_SUGGESTION."' THEN 1 ELSE 0 END) as reply_suggestions"),
                DB::raw('SUM(input_tokens + cached_input_tokens) as input_tokens'),
                DB::raw('SUM(output_tokens + reasoning_tokens) as output_tokens'),
                DB::raw('SUM(cost_micros) as cost_micros'),
            ])
            ->map(fn (AiUsage $row): array => [
                'userId' => $row->user_id,
                'name' => $row->user_id === null ? 'System' : (string) $row->name,
                'email' => $row->email,
                'requests' => (int) $row->requests,
                'replySuggestions' => (int) $row->reply_suggestions,
                'inputTokens' => (int) $row->input_tokens,
                'outputTokens' => (int) $row->output_tokens,
                'costMicros' => (int) $row->cost_micros,
            ]);
    }

    /**
     * Gemini sometimes reports the model as "models/<name>".
     */
    private function modelName(?string $model): ?string
    {
        return $model === null
            ? null
            : str($model)
                ->replace('models/', '')
                ->toString();
    }
}
