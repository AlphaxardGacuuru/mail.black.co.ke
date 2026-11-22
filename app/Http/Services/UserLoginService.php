<?php

namespace App\Http\Services;

use App\Models\UserLogin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserLoginService extends Service
{
    /**
     * Sign-ins, newest first, optionally narrowed to users whose name or
     * email matches.
     */
    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        return UserLogin::query()
            ->with('user:id,name,email,avatar,last_active_at')
            ->when($search, function (Builder $query) use ($search) {
                $query->whereHas('user', fn (Builder $user) => $user->search($search));
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Sign-ins and distinct users signing in per day, for the last $days
     * days including today. Days without any are filled with zeros.
     *
     * @return Collection<int, array{date: string, logins: int, users: int}>
     */
    public function daily(int $days): Collection
    {
        $rows = UserLogin::query()
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as logins, COUNT(DISTINCT user_id) as users')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        return collect(range($days - 1, 0))
            ->map(fn (int $offset) => now()->subDays($offset)->toDateString())
            ->map(fn (string $date) => [
                'date' => $date,
                'logins' => (int) ($rows[$date]->logins ?? 0),
                'users' => (int) ($rows[$date]->users ?? 0),
            ])
            ->values();
    }
}
