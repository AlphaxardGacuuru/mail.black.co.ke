import { useQuery } from "@tanstack/react-query"
import StoryController from "@/actions/App/Http/Controllers/StoryController"
import Axios from "@/lib/axios"
import type { Story } from "@/types/story"

export const storyKeys = {
	all: ["stories"] as const,
	one: (id: string) => ["stories", id] as const,
}

/**
 * The user's stories from the last day: their daily news.
 */
export function useStories() {
	return useQuery({
		queryKey: storyKeys.all,
		queryFn: () =>
			Axios.get<{ data: Story[] }>(StoryController.index.url()).then(
				(response) => response.data.data
			),
	})
}

export function useStory(id: string) {
	return useQuery({
		queryKey: storyKeys.one(id),
		queryFn: () =>
			Axios.get<{ data: Story }>(StoryController.show.url(id)).then(
				(response) => response.data.data
			),
	})
}

export function markStorySeen(id: string) {
	return Axios.post(StoryController.seen.url(id))
}
