<?php

namespace App\Http\Services;

use App\Models\SeenStory;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The stories a user can see from the last day: news for the career fields
 * their CV is tagged with (so an engineer doesn't get accounting news),
 * and any story that isn't for a particular field.
 */
class StoryService extends Service
{
    /**
     * @return Collection<int, Story>
     */
    public function index(User $user): Collection
    {
        return $this->visibleTo($user)
            ->latest()
            ->get();
    }

    public function show(User $user, string $id): Story
    {
        return $this->visibleTo($user)
            ->findOrFail($id);
    }

    public function seen(User $user, string $id): SeenStory
    {
        $story = $this->show($user, $id);

        return SeenStory::firstOrCreate(
            ['story_id' => $story->id, 'user_id' => $user->id],
            ['seen_at' => now()],
        );
    }

    /**
     * @return Builder<Story>
     */
    protected function visibleTo(User $user): Builder
    {
        return Story::query()
            ->with(['user', 'seen'])
            ->where('created_at', '>', now()->subDay())
            ->where(fn (Builder $query) => $query
                ->whereNull('career_field')
                ->orWhereIn('career_field', $user->jobSeekerProfile?->career_fields ?? []));
    }
}
