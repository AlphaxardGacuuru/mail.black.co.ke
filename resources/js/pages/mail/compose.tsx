import { useNavigate } from "@tanstack/react-router"
import MailComposePane from "@/components/mail/MailComposePane"
import MailShell from "@/components/mail/MailShell"
import { useIsMobile } from "@/hooks/use-mobile"
import { Head } from "@/lib/spa"

export default function MailCompose() {
	const isMobile = useIsMobile()
	const navigate = useNavigate()

	if (isMobile) {
		return (
			<>
				<Head title="New message" />

				<div className="flex h-[calc(100vh-4rem)] flex-col border rounded-lg overflow-hidden">
					<MailComposePane
						variant="page"
						onBack={() => navigate({ to: "/mail" })}
						onSent={() => navigate({ to: "/mail" })}
					/>
				</div>
			</>
		)
	}

	return (
		<>
			<Head title="New message" />
			<MailShell
				folder="inbox"
				initialPane={{ type: "compose" }}
			/>
		</>
	)
}
