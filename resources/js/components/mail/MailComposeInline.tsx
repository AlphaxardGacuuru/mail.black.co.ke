import { Forward, Reply, ReplyAll } from "lucide-react"
import { useState } from "react"
import MailComposeForm from "@/components/mail/MailComposeForm"
import { Button } from "@/components/ui/button"
import type { MailMessage } from "@/types/mail"

type Props = {
	parentMessage: MailMessage
	currentUserEmail?: string
	onSent: () => void
}

export default function MailComposeInline({
	parentMessage,
	currentUserEmail,
	onSent,
}: Props) {
	const [activeMode, setActiveMode] = useState<
		"reply" | "reply-all" | "forward" | null
	>(null)

	if (!activeMode) {
		return (
			<div className="flex items-center justify-end gap-2 border-t pt-3">
				<Button
					variant="outline"
					onClick={() => setActiveMode("reply")}>
					<Reply className="size-4" />
					Reply
				</Button>
				<Button
					variant="outline"
					onClick={() => setActiveMode("reply-all")}>
					<ReplyAll className="size-4" />
					Reply All
				</Button>
				<Button
					variant="outline"
					onClick={() => setActiveMode("forward")}>
					<Forward className="size-4" />
					Forward
				</Button>
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
		<div className="border-t pt-3">
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
