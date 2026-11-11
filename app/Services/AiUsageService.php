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
     * is a system-wide request that no single user asked for. $searches is
     * how many Google searches grounded the answer, billed on top.
     */
    public function record(?string $userId, string $feature, TextResponse $response, ?Model $subject = null, ?int $attempt = null, int $searches = 0): AiUsage
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
            'searches' => $searches,
            'cost_micros' => $this->costInMicros(
                $model,
                $usage->promptTokens,
                $usage->cacheReadInputTokens,
                $usage->completionTokens + $usage->reasoningTokens,
            ) + $this->searchCostInMicros($searches),
        ]);
    }

    /**
     * Record a paid service that isn't billed in tokens, e.g. a CAPTCHA
     * solve, so it's counted with the AI spend. $model names what was
     * bought (the CAPTCHA type).
     */
    public function recordService(?string $userId, string $feature, string $provider, string $model, int $costMicros, ?Model $subject = null): AiUsage
    {
        return AiUsage::create([
            'user_id' => $userId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'cost_micros' => $costMicros,
        ]);
    }

    /**
     * A price per 1,000 searches in dollars is a price per search in
     * thousandths of a dollar, so times 1,000 for micro-dollars.
     */
    public function searchCostInMicros(int $searches): int
    {
        return (int) round($searches * (float) config('ai.search_pricing') * 1000);
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
     * first, with what each feature cost them. Requests without a user are
     * totalled together as "System".
     *
     * @return Collection<int, array{userId: string|null, name: string, email: string|null, requests: int, replySuggestions: int, inputTokens: int, outputTokens: int, costMicros: int, features: array<int, array<string, mixed>>}>
     */
    public function monthlyTotals(CarbonImmutable $month): Collection
    {
        $features = $this->featureTotals($month, byUser: true)
            ->groupBy(fn (array $row) => $row['userId'] ?? '');

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
                'name' => $row->user_id === null ? 'System' : ($row->name ?? 'Deleted user'),
                'email' => $row->email,
                'requests' => (int) $row->requests,
                'replySuggestions' => (int) $row->reply_suggestions,
                'inputTokens' => (int) $row->input_tokens,
                'outputTokens' => (int) $row->output_tokens,
                'costMicros' => (int) $row->cost_micros,
                'features' => $features
                    ->get($row->user_id ?? '', collect())
                    ->map(fn (array $feature) => collect($feature)
                        ->except('userId')
                        ->all())
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * What each feature cost in the month, most expensive first, for
     * pricing: how many requests and users, how many things it was used on
     * (e.g. CVs revamped, applications drafted), and the average cost of
     * each. A thing can take several requests (a revamp done again with
     * answers), so the cost per item is what one costs in practice.
     *
     * @return Collection<int, array{userId?: string|null, feature: string, requests: int, users: int, items: int, searches: int, costMicros: int, costPerRequestMicros: int, costPerItemMicros: int}>
     */
    public function featureTotals(CarbonImmutable $month, bool $byUser = false): Collection
    {
        return AiUsage::query()
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->when($byUser, fn ($query) => $query->groupBy('user_id'))
            ->groupBy('feature')
            ->orderByDesc('cost_micros')
            ->get([
                ...($byUser ? ['user_id'] : []),
                'feature',
                DB::raw('COUNT(*) as requests'),
                DB::raw('COUNT(DISTINCT user_id) as users'),
                // A request about nothing in particular counts as its own item.
                DB::raw("COUNT(DISTINCT COALESCE(CONCAT(subject_type, ':', subject_id), id)) as items"),
                DB::raw('SUM(searches) as searches'),
                DB::raw('SUM(cost_micros) as cost_micros'),
            ])
            ->map(fn (AiUsage $row): array => [
                ...($byUser ? ['userId' => $row->user_id] : []),
                'feature' => $row->feature,
                'requests' => (int) $row->requests,
                'users' => (int) $row->users,
                'items' => (int) $row->items,
                'searches' => (int) $row->searches,
                'costMicros' => (int) $row->cost_micros,
                'costPerRequestMicros' => (int) round($row->cost_micros / max(1, $row->requests)),
                'costPerItemMicros' => (int) round($row->cost_micros / max(1, $row->items)),
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
