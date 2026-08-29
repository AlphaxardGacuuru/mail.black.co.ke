import { useNavigate } from "@tanstack/react-router"
import MailShell from "@/components/mail/MailShell"
import MailThreadView from "@/components/mail/MailThreadView"
import { useIsMobile } from "@/hooks/use-mobile"
import { Head } from "@/lib/spa"

export default function MailSentThread({ id }: { id: string }) {
	const isMobile = useIsMobile()
	const navigate = useNavigate()

	if (isMobile) {
		return (
			<>
				<Head title="Sent mail" />
				<div className="flex h-[calc(100vh-4rem)] flex-col overflow-hidden rounded-lg border">
					<MailThreadView
						threadId={id}
						variant="page"
						onBack={() => navigate({ to: "/mail/sent" })}
					/>
				</div>
			</>
		)
	}

	return (
		<>
			<Head title="Sent mail" />
			<MailShell
				folder="sent"
				initialPane={{ type: "thread", id }}
			/>
		</>
	)
}
