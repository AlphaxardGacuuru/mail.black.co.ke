import { useState } from "react"
import MailComposeForm from "@/components/mail/MailComposeForm"
import { Button } from "@/components/ui/button"
import type { MailMessage } from "@/types/mail"

type Props = {
	parentMessage: MailMessage
	currentUserEmail?: string
	onSent: () => void
}

export default function MailComposeInline({ parentMessage, currentUserEmail, onSent }: Props) {
	const [activeMode, setActiveMode] = useState<"reply" | "reply-all" | "forward" | null>(null)

	if (!activeMode) {
		return (
			<div className="flex items-center gap-2 border-t pt-3">
				<Button
					variant="outline"
					onClick={() => setActiveMode("reply")}>
					Reply
				</Button>
				<Button
					variant="outline"
					onClick={() => setActiveMode("reply-all")}>
					Reply All
				</Button>
				<Button
					variant="outline"
					onClick={() => setActiveMode("forward")}>
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
