import { formatDistanceToNow } from "date-fns"
import { useNavigate } from "@tanstack/react-router"
import { useQueryClient } from "@tanstack/react-query"
import {
	ChevronLeft,
	ChevronRight,
	ExternalLink,
	Play,
	Volume2,
	VolumeX,
	X,
} from "lucide-react"
import { useCallback, useEffect, useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { Link } from "@/components/ui/link"
import { AvatarVerifiedBadge } from "@/components/verified-badge"
import { JOB_APPLICATIONS_URL } from "@/lib/job-application-tabs"
import { cn } from "@/lib/utils"
import { markStorySeen, storyKeys } from "@/queries/stories"
import type { Story } from "@/types/story"

/** How long an image segment shows, in ms (OLD: about 5s). */
const IMAGE_DURATION = 5000
const TICK = 50
/** Mark a story seen once this much of a segment has played (OLD: 3s of 5s). */
const SEEN_AT = 0.6

type Props = {
	stories: Story[]
	startId: string
}

/**
 * The full-screen story player, from Black Music: one progress bar per
 * segment, auto-advance through segments and on to the next story, tap
 * left or right to step, tap the middle (or hold) to pause, and a sound
 * toggle for videos. A news segment links to its article below the card.
 */
export function StoryViewer({ stories, startId }: Props) {
	const navigate = useNavigate()
	const queryClient = useQueryClient()

	const [storyIndex, setStoryIndex] = useState(() =>
		Math.max(
			0,
			stories.findIndex((story) => story.id === startId)
		)
	)
	const [segment, setSegment] = useState(0)
	const [progress, setProgress] = useState(0)
	const [paused, setPaused] = useState(false)
	const [soundOff, setSoundOff] = useState(false)
	const videoRef = useRef<HTMLVideoElement>(null)
	const seenIds = useRef(new Set<string>())
	const holdTimer = useRef<number>(undefined)
	const wasHeld = useRef(false)

	const story = stories[storyIndex]
	const media = story?.media[segment]
	const isVideo = Boolean(media?.video)
	const isPaused = paused

	const close = useCallback(() => {
		void queryClient.invalidateQueries({ queryKey: storyKeys.all })
		void navigate({ to: JOB_APPLICATIONS_URL as never })
	}, [navigate, queryClient])

	const goToStory = useCallback(
		(index: number) => {
			setStoryIndex(index)
			setSegment(0)
			setProgress(0)
			// Keep the URL on the story being watched, so it can be shared
			window.history.replaceState(
				window.history.state,
				"",
				`/stories/${stories[index].id}/view`
			)
		},
		[stories]
	)

	const next = useCallback(() => {
		if (!story) {
			return
		}

		if (segment < story.media.length - 1) {
			setSegment(segment + 1)
			setProgress(0)
		} else if (storyIndex < stories.length - 1) {
			goToStory(storyIndex + 1)
		} else {
			close()
		}
	}, [close, goToStory, segment, stories.length, story, storyIndex])

	const previous = useCallback(() => {
		if (segment > 0) {
			setSegment(segment - 1)
			setProgress(0)
		} else if (storyIndex > 0) {
			goToStory(storyIndex - 1)
		} else {
			setProgress(0)
		}
	}, [goToStory, segment, storyIndex])

	// Image segments: tick the progress bar
	useEffect(() => {
		if (isPaused || isVideo || !story) {
			return
		}

		const timer = window.setInterval(
			() => setProgress((p) => Math.min(1, p + TICK / IMAGE_DURATION)),
			TICK
		)

		return () => window.clearInterval(timer)
	}, [isPaused, isVideo, story, segment])

	// Move on when a segment finishes
	useEffect(() => {
		if (progress >= 1) {
			next()
		}
	}, [progress, next])

	// Video segments: follow the pause state, falling back to muted autoplay
	useEffect(() => {
		const video = videoRef.current

		if (!video) {
			return
		}

		if (isPaused) {
			video.pause()
		} else {
			video.play().catch(() => {
				video.muted = true
				setSoundOff(true)
				void video.play().catch(() => undefined)
			})
		}
	}, [isPaused, isVideo, segment, storyIndex])

	// Mark the story seen after a few seconds
	useEffect(() => {
		if (!story || seenIds.current.has(story.id)) {
			return
		}

		if (progress >= SEEN_AT || segment > 0) {
			seenIds.current.add(story.id)

			if (!story.hasSeen) {
				markStorySeen(story.id).catch(() => undefined)
			}
		}
	}, [progress, segment, story])

	// Keyboard: arrows step, space pauses, escape closes
	useEffect(() => {
		const onKey = (event: KeyboardEvent) => {
			if (event.key === "ArrowRight") {
				next()
			}
			if (event.key === "ArrowLeft") {
				previous()
			}
			if (event.key === "Escape") {
				close()
			}
			if (event.key === " ") {
				event.preventDefault()
				setPaused((p) => !p)
			}
		}

		window.addEventListener("keydown", onKey)

		return () => window.removeEventListener("keydown", onKey)
	}, [close, next, previous])

	// No page scrolling behind the player
	useEffect(() => {
		const overflow = document.body.style.overflow
		document.body.style.overflow = "hidden"

		return () => {
			document.body.style.overflow = overflow
		}
	}, [])

	if (!story || !media) {
		return null
	}

	/* Tap zones: a quick tap steps or pauses, holding pauses until release */
	const onPointerDown = () => {
		wasHeld.current = false
		holdTimer.current = window.setTimeout(() => {
			wasHeld.current = true
			setPaused(true)
		}, 250)
	}

	const onPointerUp = (zone: "left" | "center" | "right") => {
		window.clearTimeout(holdTimer.current)

		if (wasHeld.current) {
			setPaused(false)

			return
		}

		if (zone === "left") {
			previous()
		}
		if (zone === "right") {
			next()
		}
		if (zone === "center") {
			setPaused((p) => !p)
		}
	}

	const toggleSound = () => {
		const video = videoRef.current
		const off = !soundOff

		setSoundOff(off)

		if (video) {
			video.muted = off
		}
	}

	return (
		<div className="fixed inset-0 z-[100] flex items-center justify-center bg-black text-white">
			<div className="relative h-full w-full overflow-hidden sm:aspect-[9/16] sm:w-auto sm:rounded-2xl">
				{/* Media */}
				{isVideo ? (
					<video
						key={`${story.id}-${segment}`}
						ref={videoRef}
						src={`/storage/${media.video}`}
						playsInline
						autoPlay
						muted={soundOff}
						controls={false}
						controlsList="nodownload"
						onTimeUpdate={(event) => {
							const video = event.currentTarget
							if (video.duration) {
								setProgress(video.currentTime / video.duration)
							}
						}}
						onEnded={next}
						className="size-full object-contain"
					/>
				) : (
					<img
						key={`${story.id}-${segment}`}
						loading="eager"
						src={`/storage/${media.image}`}
						alt={media.title ?? story.text ?? story.name}
						className="size-full object-contain"
					/>
				)}

				{/* Tap zones */}
				<div className="absolute inset-0 flex select-none">
					{(["left", "center", "right"] as const).map((zone) => (
						<button
							key={zone}
							type="button"
							aria-label={
								zone === "left"
									? "Previous"
									: zone === "right"
										? "Next"
										: isPaused
											? "Play"
											: "Pause"
							}
							onPointerDown={onPointerDown}
							onPointerUp={() => onPointerUp(zone)}
							onPointerLeave={() => window.clearTimeout(holdTimer.current)}
							onContextMenu={(event) => event.preventDefault()}
							className={cn(
								"h-full outline-none",
								zone === "center" ? "flex-[2]" : "flex-1"
							)}
						/>
					))}
				</div>

				{/* Top: progress bars and header */}
				<div className="pointer-events-none absolute inset-x-0 top-0 bg-linear-to-b from-black/70 to-transparent px-3 pb-10 pt-[max(0.75rem,env(safe-area-inset-top))]">
					<div className="flex gap-1">
						{story.media.map((_, index) => (
							<div
								key={index}
								className="h-0.5 flex-1 overflow-hidden rounded-full bg-white/30">
								<div
									className="h-full bg-primary"
									style={{
										width:
											index < segment
												? "100%"
												: index === segment
													? `${progress * 100}%`
													: "0%",
									}}
								/>
							</div>
						))}
					</div>

					<div className="pointer-events-auto mt-3 flex items-center gap-3">
						<span className="relative shrink-0">
							<img
								src={story.avatar ?? "/android-chrome-192x192.png"}
								alt=""
								className="size-9 rounded-full border-2 border-white/80 object-cover"
							/>
							{story.verified && <AvatarVerifiedBadge />}
						</span>
						<div className="min-w-0 flex-1">
							<p className="truncate text-sm font-semibold">{story.name}</p>
							<p className="truncate text-xs text-white/70">
								{formatDistanceToNow(new Date(story.createdAt), {
									addSuffix: true,
								})}
							</p>
						</div>

						{isVideo && (
							<Button
								variant="ghost"
								size="icon"
								onClick={toggleSound}
								aria-label={soundOff ? "Unmute sound" : "Mute sound"}
								className="text-white hover:bg-white/15 hover:text-white">
								{soundOff ? (
									<VolumeX className="size-5" />
								) : (
									<Volume2 className="size-5" />
								)}
							</Button>
						)}
						<Button
							variant="ghost"
							size="icon"
							onClick={close}
							aria-label="Close"
							className="text-white hover:bg-white/15 hover:text-white">
							<X className="size-6" />
						</Button>
					</div>
				</div>

				{/* Caption, and the article a news segment is about */}
				{(story.text || media.url) && (
					<div className="pointer-events-none absolute inset-x-0 bottom-0 flex flex-col items-center gap-3 bg-linear-to-t from-black/80 to-transparent px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-16">
						{story.text && (
							<p className="whitespace-pre-line text-center text-[15px] leading-relaxed">
								{story.text}
							</p>
						)}
						{media.url && (
							<Link
								href={media.url}
								target="_blank"
								rel="noopener noreferrer"
								variant="outline"
								size="sm"
								onClick={() => setPaused(true)}
								className="pointer-events-auto normal-case">
								Read on {media.source ?? "the site"}
								<ExternalLink />
							</Link>
						)}
					</div>
				)}

				{isPaused && (
					<div className="pointer-events-none absolute inset-0 flex items-center justify-center">
						<span className="rounded-full bg-black/40 p-4">
							<Play className="size-8 fill-current" />
						</span>
					</div>
				)}
			</div>

			{/* Desktop step buttons beside the frame */}
			<Button
				variant="ghost"
				size="icon"
				onClick={previous}
				aria-label="Previous"
				className="absolute left-4 top-1/2 hidden size-11 -translate-y-1/2 bg-white/10 text-white hover:bg-white/20 hover:text-white sm:inline-flex">
				<ChevronLeft className="size-6" />
			</Button>
			<Button
				variant="ghost"
				size="icon"
				onClick={next}
				aria-label="Next"
				className="absolute right-4 top-1/2 hidden size-11 -translate-y-1/2 bg-white/10 text-white hover:bg-white/20 hover:text-white sm:inline-flex">
				<ChevronRight className="size-6" />
			</Button>
		</div>
	)
}
