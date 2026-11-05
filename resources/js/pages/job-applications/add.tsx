import { useNavigate } from "@tanstack/react-router"
import { ImagePlus, LoaderCircle, X } from "lucide-react"
import { useEffect, useState } from "react"
import type { FormEvent } from "react"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Input } from "@/components/ui/input"
import {
	JOB_APPLICATIONS_URL,
	jobApplicationTabUrl,
} from "@/lib/job-application-tabs"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { errorMessage, useAddSharedPost } from "@/queries/job-search"
import type { JobApplicationStatus, JobApplicationTab } from "@/types"

const MAX_IMAGES = 4

// The tab a newly added job lands on.
const TAB_FOR_STATUS: Partial<Record<JobApplicationStatus, JobApplicationTab>> =
	{
		pending_approval: "review",
		apply_manually: "manual",
		skipped: "closed",
	}

/**
 * The post the service worker kept from a share to the installed app (see
 * sw.ts), removed once read.
 */
async function takeSharedPost(): Promise<{
	text: string
	url: string
	images: File[]
} | null> {
	if (!("caches" in window)) {
		return null
	}

	const cache = await caches.open("shared-post")
	const post = await cache.match("/shared-post/post")

	if (!post) {
		return null
	}

	const { title, text, url, images } = (await post.json()) as {
		title: string
		text: string
		url: string
		images: number
	}

	const files = await Promise.all(
		Array.from({ length: images }, async (_, index) => {
			const response = await cache.match(`/shared-post/image-${index}`)

			if (!response) {
				return null
			}

			const blob = await response.blob()
			const name = decodeURIComponent(
				response.headers.get("X-File-Name") ?? `image-${index}`
			)

			return new File([blob], name, { type: blob.type })
		})
	)

	await caches.delete("shared-post")

	return {
		text: [title, text].filter(Boolean).join("\n\n"),
		url,
		images: files.filter((file): file is File => file !== null),
	}
}

function ImagePreview({
	file,
	onRemove,
}: {
	file: File
	onRemove: () => void
}) {
	const [src, setSrc] = useState<string | null>(null)

	useEffect(() => {
		const url = URL.createObjectURL(file)
		setSrc(url)

		return () => URL.revokeObjectURL(url)
	}, [file])

	return (
		<div className="relative size-24 overflow-hidden rounded-lg border">
			{src && (
				<img
					src={src}
					alt={file.name}
					className="size-full object-cover"
				/>
			)}
			<button
				type="button"
				aria-label={`Remove ${file.name}`}
				onClick={onRemove}
				className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white">
				<X className="size-3" />
			</button>
		</div>
	)
}

/**
 * Add a job from a post seen elsewhere (a WhatsApp group, Telegram,
 * social media): its text, a link, or pictures of it. Shares to the
 * installed app open here with the post filled in.
 */
export default function AddJob() {
	const navigate = useNavigate()
	const add = useAddSharedPost()
	const [text, setText] = useState("")
	const [url, setUrl] = useState("")
	const [images, setImages] = useState<File[]>([])

	useEffect(() => {
		if (!new URLSearchParams(window.location.search).has("shared")) {
			return
		}

		takeSharedPost().then((post) => {
			if (post) {
				setText(post.text)
				setUrl(post.url)
				setImages(post.images.slice(0, MAX_IMAGES))
			}
		})
	}, [])

	function addImages(files: FileList | null): void {
		const picked = Array.from(files ?? []).filter((file) =>
			file.type.startsWith("image/")
		)

		setImages((current) => [...current, ...picked].slice(0, MAX_IMAGES))
	}

	function submit(event: FormEvent): void {
		event.preventDefault()

		add
			.mutateAsync({ text, url, images })
			.then((data) => {
				toast.success(data.message)

				const tab = TAB_FOR_STATUS[data.data[0]?.status] ?? "review"

				navigate({ to: jobApplicationTabUrl(tab) as never })
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to add this job."))
			)
	}

	return (
		<>
			<Head title="Add a job" />

			<form
				onSubmit={submit}
				className="mx-auto w-full max-w-3xl space-y-6">
				<Heading
					title="Add a job"
					description="Seen a job in a WhatsApp group or on social media? Paste the post or add pictures of it, and it's added to your applications."
				/>

				<GlassCard className="space-y-4 p-4 sm:p-5">
					{/* Post Start */}
					<textarea
						aria-label="Job post"
						placeholder="Paste the job post here"
						value={text}
						rows={8}
						onChange={(event) => setText(event.target.value)}
						className="w-full rounded-lg border border-neutral-300 bg-transparent p-4 font-nunito text-sm font-light focus:border-neutral-500 focus:ring-2 focus:ring-neutral-400/20 focus:outline-none dark:border-white/20"
					/>
					{/* Post End */}

					{/* Link Start */}
					<Input
						label="Link (optional)"
						type="url"
						value={url}
						onChange={(event) => setUrl(event.target.value)}
					/>
					{/* Link End */}

					{/* Images Start */}
					<div className="space-y-2">
						<div className="flex flex-wrap gap-2">
							{images.map((image, index) => (
								<ImagePreview
									key={`${image.name}-${index}`}
									file={image}
									onRemove={() =>
										setImages(images.filter((_, other) => other !== index))
									}
								/>
							))}
							{images.length < MAX_IMAGES && (
								<label className="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-xs text-muted-foreground hover:text-foreground">
									<ImagePlus className="size-5" />
									Add picture
									<input
										type="file"
										accept="image/*"
										multiple
										className="sr-only"
										onChange={(event) => {
											addImages(event.target.files)
											event.target.value = ""
										}}
									/>
								</label>
							)}
						</div>
						<p className="text-xs text-muted-foreground">
							Job posters and screenshots work too, up to {MAX_IMAGES}.
						</p>
					</div>
					{/* Images End */}
				</GlassCard>

				<p className="text-xs text-muted-foreground">
					On Android, with the app installed, you can also tap Share on a post
					in WhatsApp and choose Black Mail.
				</p>

				<div className="flex justify-end gap-2">
					<Button
						type="button"
						variant="secondary"
						onClick={() => navigate({ to: JOB_APPLICATIONS_URL as never })}>
						Cancel
					</Button>
					<Button
						disabled={
							add.isPending || (!text.trim() && !url.trim() && !images.length)
						}>
						{add.isPending && <LoaderCircle className="size-4 animate-spin" />}
						{add.isPending ? "Reading the post…" : "Add job"}
					</Button>
				</div>
			</form>
		</>
	)
}

AddJob.layout = {
	breadcrumbs: [
		{ title: "Job applications", href: JOB_APPLICATIONS_URL },
		{ title: "Add a job", href: `${JOB_APPLICATIONS_URL}/add` },
	],
}
