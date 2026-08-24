import { useNavigate } from "@tanstack/react-router"
import MailThreadView from "@/components/mail/MailThreadView"
import { Head } from "@/lib/spa"

export default function MailShow({ id }: { id: string }) {
	const navigate = useNavigate()

	return (
		<>
			<Head title="Mail" />

			<div className="flex h-[calc(100vh-4rem)] flex-col border rounded-lg overflow-hidden">
				<MailThreadView
					threadId={id}
					variant="page"
					onBack={() => navigate({ to: "/mail" })}
				/>
			</div>
		</>
	)
}
