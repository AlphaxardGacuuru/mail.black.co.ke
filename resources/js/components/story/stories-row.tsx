import { StoryCard } from "@/components/story/story-card"
import { cn } from "@/lib/utils"
import { useStories } from "@/queries/stories"

/** The stories from the last day, side by side, posted by Black Mail. */
export function StoriesRow({ className }: { className?: string }) {
	const { data: stories } = useStories()

	if (!stories?.length) {
		return null
	}

	return (
		<div className={cn("flex snap-x gap-3 overflow-x-auto", className)}>
			{stories.map((story) => (
				<StoryCard
					key={story.id}
					story={story}
				/>
			))}
		</div>
	)
}
