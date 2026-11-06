import { Link } from "@/components/ui/link"
import { AvatarVerifiedBadge } from "@/components/verified-badge"
import { cn } from "@/lib/utils"
import type { Story } from "@/types/story"

/** A tall story thumbnail, ringed in the primary colour until seen. */
export function StoryCard({ story }: { story: Story }) {
	const cover = story.media[0]

	return (
		<Link
			href={`/stories/${story.id}/view`}
			variant="unstyled"
			className="group w-28 shrink-0 snap-start sm:w-32">
			<div
				className={cn(
					"relative aspect-[9/16] overflow-hidden rounded-2xl border-2 bg-muted",
					story.hasSeen ? "border-border" : "border-primary"
				)}>
				{cover?.image && (
					<img
						src={`/storage/${cover.image}`}
						alt=""
						className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
					/>
				)}
				{!cover?.image && cover?.video && (
					<video
						src={`/storage/${cover.video}`}
						muted
						playsInline
						preload="metadata"
						className="size-full object-cover"
					/>
				)}
				<div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/80 to-transparent p-2 pt-8">
					<span className="relative mb-1 block size-7">
						<span className="block size-full overflow-hidden rounded-full border-2 border-white/80 bg-primary/40">
							<img
								src={story.avatar ?? "/android-chrome-192x192.png"}
								alt=""
								className="size-full object-cover"
							/>
						</span>
						{story.verified && <AvatarVerifiedBadge className="size-3" />}
					</span>
					<p className="truncate text-xs font-semibold text-white">
						{story.name}
					</p>
					<p className="line-clamp-2 text-[11px] text-white/70">
						{story.text ??
							`${story.media.length} ${story.media.length === 1 ? "story" : "stories"}`}
					</p>
				</div>
			</div>
		</Link>
	)
}
