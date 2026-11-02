<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiUsageController extends Controller
{
    public function __construct(protected AiUsageService $service) {}

    /**
     * AI requests, tokens and cost per user for a month (this month by default).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::now()
                ->startOfMonth();

        $users = $this->service->monthlyTotals($month);

        return response()->json([
            'month' => $month->format('Y-m'),
            'users' => $users,
            'totals' => [
                'requests' => $users->sum('requests'),
                'replySuggestions' => $users->sum('replySuggestions'),
                'inputTokens' => $users->sum('inputTokens'),
                'outputTokens' => $users->sum('outputTokens'),
                'costMicros' => $users->sum('costMicros'),
            ],
        ]);
    }
}
