<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\UserLoginService;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserLoginController extends Controller
{
    public const CHART_DAYS = 30;

    public function __construct(protected UserLoginService $service) {}

    /**
     * Who signed in, how and from where, plus active users and sign-ins
     * per day.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $request->string('search')
            ->trim()
            ->toString();

        $logins = $this->service
            ->paginate($search, $request->integer('per_page', 20));

        return response()->json([
            'data' => $logins
                ->getCollection()
                ->map(fn (UserLogin $login) => [
                    'id' => $login->id,
                    'method' => $login->method,
                    'ipAddress' => $login->ip_address,
                    'userAgent' => $login->user_agent,
                    'createdAt' => $login->created_at->toIso8601String(),
                    'user' => [
                        'id' => $login->user->id,
                        'name' => $login->user->name,
                        'email' => $login->user->email,
                        'avatar' => $login->user->avatar,
                        'lastActiveAt' => $login->user->last_active_at?->toIso8601String(),
                    ],
                ]),
            'meta' => [
                'current_page' => $logins->currentPage(),
                'last_page' => $logins->lastPage(),
                'total' => $logins->total(),
                'activeUsers' => User::activeCounts(),
                'daily' => $this->service->daily(self::CHART_DAYS),
            ],
        ]);
    }
}
