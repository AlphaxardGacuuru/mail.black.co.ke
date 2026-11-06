import { CirclePlay } from "lucide-react"
import Heading from "@/components/heading"
import { StoryCard } from "@/components/story/story-card"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import { useStories } from "@/queries/stories"

/**
 * Every story from the last day: news for the user's career fields,
 * unseen ones ringed in the primary colour.
 */
export default function Stories() {
	const { data: stories = [], isLoading } = useStories()

	return (
		<>
			<Head title="Stories" />

			<div className="mx-auto w-full max-w-3xl space-y-6">
				<Heading
					title="Stories"
					description="Career news for your field from the last day."
				/>

				{isLoading && (
					<div className="flex flex-wrap gap-3">
						{[0, 1, 2].map((index) => (
							<Skeleton
								key={index}
								className="aspect-[9/16] w-28 rounded-2xl sm:w-32"
							/>
						))}
					</div>
				)}

				{!isLoading && stories.length === 0 && (
					<div className="flex flex-col items-center gap-3 py-16 text-center text-muted-foreground">
						<CirclePlay className="size-10" />
						<p>No stories yet. New ones arrive every morning.</p>
					</div>
				)}

				{stories.length > 0 && (
					<div className="flex flex-wrap gap-3">
						{stories.map((story) => (
							<StoryCard
								key={story.id}
								story={story}
							/>
						))}
					</div>
				)}
			</div>
		</>
	)
}
