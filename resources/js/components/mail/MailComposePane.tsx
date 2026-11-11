import { ArrowLeft, X } from "lucide-react"
import MailComposeForm from "@/components/mail/MailComposeForm"
import { Button } from "@/components/ui/button"
import type { MailDraft } from "@/lib/mailto"

type Props = {
	variant: "pane" | "page"
	onClose?: () => void
	onBack?: () => void
	onSent: (result: { threadId?: string }) => void
	draft?: MailDraft | null
}

export default function MailComposePane({
	variant,
	onClose,
	onBack,
	onSent,
	draft,
}: Props) {
	return (
		<div className="flex flex-1 flex-col overflow-hidden">
			<div className="flex items-center gap-2 border-b p-3">
				{variant === "page" && (
					<Button
						variant="ghost"
						size="icon"
						onClick={onBack}>
						<ArrowLeft className="size-4" />
					</Button>
				)}

				<h2 className="flex-1 truncate font-medium">New message</h2>

				{variant === "pane" && (
					<Button
						variant="ghost"
						size="icon"
						onClick={onClose}>
						<X className="size-4" />
					</Button>
				)}
			</div>

			<div className="flex-1 overflow-y-auto p-3">
				<MailComposeForm
					mode="new"
					initialTo={draft?.to}
					initialCc={draft?.cc}
					initialBcc={draft?.bcc}
					initialSubject={draft?.subject}
					initialBody={draft?.body}
					onSent={onSent}
					onCancel={variant === "pane" ? onClose : onBack}
				/>
			</div>
		</div>
	)
}
