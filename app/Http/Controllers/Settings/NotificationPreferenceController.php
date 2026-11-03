<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings → Notifications: for each category in config/notifications.php,
 * whether it arrives in the app, by push and by email.
 */
class NotificationPreferenceController extends Controller
{
    // Request/response names => stored names.
    protected const CHANNELS = ['inApp' => 'in_app', 'push' => 'push', 'email' => 'email'];

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()));
    }

    /**
     * Accepts any subset of categories and channels, so the page can save one
     * switch at a time.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $categories = array_keys(config('notifications.categories'));

        $data = $request->validate([
            'preferences' => ['required', 'array', function (string $attribute, mixed $value, $fail) use ($categories) {
                foreach ($value as $category => $channels) {
                    if (! in_array($category, $categories, true)) {
                        $fail("Unknown notification type: {$category}.");
                    } elseif (! is_array($channels) || array_diff(array_keys($channels), array_keys(self::CHANNELS))) {
                        $fail("Unknown notification channel for {$category}.");
                    }
                }
            }],
            'preferences.*.*' => 'boolean',
        ]);

        $saved = $user->notification_preferences ?? [];

        foreach ($data['preferences'] as $category => $channels) {
            foreach ($channels as $channel => $enabled) {
                $saved[$category][self::CHANNELS[$channel]] = (bool) $enabled;
            }
        }

        $user->notification_preferences = $saved;
        $user->save();

        return response()->json([
            'message' => 'Notification Settings Saved',
            ...$this->state($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(User $user): array
    {
        $preferences = $user->notificationPreferences();

        return [
            'categories' => collect(config('notifications.categories'))
                ->map(fn (array $category, string $key) => [
                    'key' => $key,
                    'group' => $category['group'],
                    'label' => $category['label'],
                    'description' => $category['description'],
                    'channels' => array_map(fn (string $stored) => $preferences[$key][$stored], self::CHANNELS),
                    // Channels that can't be switched on, with the reason.
                    'blocked' => array_filter(array_map(fn (string $stored) => $user->notificationChannelBlocked($key, $stored), self::CHANNELS)),
                ])
                ->values()
                ->all(),
            'email' => $user->email,
        ];
    }
}
