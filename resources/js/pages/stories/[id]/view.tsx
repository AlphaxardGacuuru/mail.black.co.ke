import { X } from "lucide-react"
import { StoryViewer } from "@/components/story/story-viewer"
import { Link } from "@/components/ui/link"
import { Spinner } from "@/components/ui/spinner"
import { Head } from "@/lib/spa"
import { JOB_APPLICATIONS_URL } from "@/lib/job-application-tabs"
import { useStories, useStory } from "@/queries/stories"

/**
 * Plays the user's stories full screen starting at this one. A story that
 * isn't in the list (an older link) plays on its own.
 */
export default function StoryShow({ id }: { id: string }) {
	const stories = useStories()
	const inFeed = stories.data?.some((story) => story.id === id)
	const single = useStory(id)

	const list = inFeed ? stories.data : single.data ? [single.data] : undefined
	const failed = !inFeed && single.isError && !stories.isPending

	return (
		<>
			<Head title="Story" />

			{list ? (
				<StoryViewer
					stories={list}
					startId={id}
				/>
			) : (
				<div className="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-4 bg-black text-white">
					<Link
						href={JOB_APPLICATIONS_URL}
						variant="ghost"
						size="icon"
						aria-label="Close"
						className="absolute right-4 top-4 text-white hover:bg-white/15 hover:text-white">
						<X className="size-6" />
					</Link>
					{failed ? (
						<p className="text-sm text-white/70">
							This story has expired or was deleted.
						</p>
					) : (
						<Spinner />
					)}
				</div>
			)}
		</>
	)
}
