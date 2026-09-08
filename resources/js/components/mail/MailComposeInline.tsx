import { Forward, Reply, ReplyAll } from "lucide-react"
import { useState } from "react"
import MailComposeForm from "@/components/mail/MailComposeForm"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import type { MailMessage } from "@/types/mail"

type Props = {
	parentMessage: MailMessage
	currentUserEmail?: string
	onSent: () => void
	// "page" fills the whole screen (mobile thread routes — same screen edge
	// the app bottom nav would occupy, which is why that nav hides there), so
	// the toolbar pins to the viewport just like app-bottom-nav.tsx. "pane" is
	// the bounded desktop split-view column, so it pins to that pane instead.
	variant: "pane" | "page"
}

export default function MailComposeInline({
	parentMessage,
	currentUserEmail,
	onSent,
	variant,
}: Props) {
	const [activeMode, setActiveMode] = useState<
		"reply" | "reply-all" | "forward" | null
	>(null)

	if (!activeMode) {
		return (
			<div
				className={cn(
					"pointer-events-none inset-x-0 bottom-0 z-40 px-3 pb-3",
					variant === "page" ? "fixed" : "absolute"
				)}>
				<div className="pointer-events-auto flex items-center justify-around gap-2 rounded-xl border border-white/40 bg-white/34 p-2 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20">
					<Button
						variant="outline"
						className="flex-1"
						onClick={() => setActiveMode("reply")}>
						<Reply className="size-4" />
						Reply
					</Button>
					<Button
						variant="outline"
						className="flex-1"
						onClick={() => setActiveMode("reply-all")}>
						<ReplyAll className="size-4" />
						Reply All
					</Button>
					<Button
						variant="outline"
						className="flex-1"
						onClick={() => setActiveMode("forward")}>
						<Forward className="size-4" />
						Forward
					</Button>
				</div>
			</div>
		)
	}

	const initialCc =
		activeMode === "reply-all"
			? (parentMessage.cc ?? [])
					.map((address) => address.address)
					.filter((address) => address !== currentUserEmail)
			: []

	return (
		<div className="border-t bg-card p-3">
			<MailComposeForm
				mode={activeMode}
				parentMessageId={parentMessage.id}
				initialCc={initialCc}
				initialSubject={parentMessage.subject ?? ""}
				onSent={() => {
					setActiveMode(null)
					onSent()
				}}
				onCancel={() => setActiveMode(null)}
			/>
		</div>
	)
}
