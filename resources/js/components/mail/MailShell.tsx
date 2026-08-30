import { useEffect, useState } from "react"
import { useEcho } from "@laravel/echo-react"
import { useNavigate } from "@tanstack/react-router"
import { useQueryClient } from "@tanstack/react-query"
import MailComposePane from "@/components/mail/MailComposePane"
import MailEmptyState from "@/components/mail/MailEmptyState"
import MailThreadList from "@/components/mail/MailThreadList"
import MailThreadView from "@/components/mail/MailThreadView"
import { useIsMobile } from "@/hooks/use-mobile"
import { useApp } from "@/contexts/AppContext"
import { playIncomingMailChime } from "@/lib/notification-sound"
import type { MailThreadFilters } from "@/queries/mail"
import type { MailFolderKey } from "@/types/mail"

type MailPane =
	| { type: "none" }
	| { type: "thread"; id: string }
	| { type: "compose" }

type Props = {
	folder: MailFolderKey
	labelId?: string
	initialPane?: MailPane
	onComposeSent?: (result: { threadId?: string }) => void
}

type MailRealtimeEvent = {
	threadId: string
}

const INCOMING_BANNER_DURATION_MS = 1800
const INCOMING_HIGHLIGHT_DURATION_MS = 2500

export default function MailShell({
	folder,
	labelId,
	initialPane,
	onComposeSent,
}: Props) {
	const isMobile = useIsMobile()
	const navigate = useNavigate()
	const queryClient = useQueryClient()
	const { auth } = useApp()

	const [filters, setFilters] = useState<MailThreadFilters>({
		folder,
		label: labelId,
		page: 1,
		q: "",
	})
	const [pane, setPane] = useState<MailPane>(initialPane ?? { type: "none" })
	const [incomingThreadId, setIncomingThreadId] = useState<string | null>(null)
	const [showIncomingBanner, setShowIncomingBanner] = useState(false)

	useEcho(
		`mail.${auth?.id ?? ""}`,
		"MailMessageStatusUpdatedEvent",
		(event: MailRealtimeEvent) => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
			queryClient.invalidateQueries({ queryKey: ["mail", "thread", event.threadId] })
		}
	)

	useEcho(
		`mail.${auth?.id ?? ""}`,
		"MailMessageReceivedEvent",
		(event: MailRealtimeEvent) => {
			playIncomingMailChime()
			setShowIncomingBanner(true)
			setIncomingThreadId(event.threadId)
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
			queryClient.invalidateQueries({ queryKey: ["mail", "thread", event.threadId] })
		}
	)

	useEffect(() => {
		if (!showIncomingBanner) {
			return
		}

		const timeout = setTimeout(
			() => setShowIncomingBanner(false),
			INCOMING_BANNER_DURATION_MS
		)

		return () => clearTimeout(timeout)
	}, [showIncomingBanner])

	useEffect(() => {
		if (!incomingThreadId) {
			return
		}

		const timeout = setTimeout(
			() => setIncomingThreadId(null),
			INCOMING_HIGHLIGHT_DURATION_MS
		)

		return () => clearTimeout(timeout)
	}, [incomingThreadId])

	function handleSelectThread(threadId: string) {
		if (isMobile) {
			navigate({ to: "/mail/$id/show", params: { id: threadId } })
		} else {
			setPane({ type: "thread", id: threadId })
		}
	}

	function openCompose() {
		if (isMobile) {
			navigate({ to: "/mail/compose" })
		} else {
			setPane({ type: "compose" })
		}
	}

	function closePane() {
		setPane({ type: "none" })
	}

	if (isMobile) {
		return (
			<div className="flex h-[calc(100vh-4rem)] flex-col">
				<MailThreadList
					filters={filters}
					onFiltersChange={setFilters}
					selectedThreadId={null}
					highlightThreadId={incomingThreadId}
					showIncomingBanner={showIncomingBanner}
					onSelectThread={handleSelectThread}
					onCompose={openCompose}
				/>
			</div>
		)
	}

	return (
		<div className="flex h-[calc(100vh-6rem)] gap-2">
			<section className="w-1/4 shrink-0 overflow-hidden rounded-lg border bg-card shadow-sm">
				<MailThreadList
					filters={filters}
					onFiltersChange={setFilters}
					selectedThreadId={pane.type === "thread" ? pane.id : null}
					highlightThreadId={incomingThreadId}
					showIncomingBanner={showIncomingBanner}
					onSelectThread={handleSelectThread}
					onCompose={openCompose}
				/>
			</section>

			{pane.type === "thread" && (
				<section className="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card shadow-sm">
					<MailThreadView
						threadId={pane.id}
						variant="pane"
						onClose={closePane}
					/>
				</section>
			)}

			{pane.type === "compose" && (
				<section className="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card shadow-sm">
					<MailComposePane
						variant="pane"
						onClose={closePane}
						onSent={onComposeSent ?? closePane}
					/>
				</section>
			)}

			{pane.type === "none" && (
				<section className="hidden min-w-0 flex-1 overflow-hidden rounded-lg border bg-card shadow-sm lg:flex">
					<MailEmptyState variant="no-selection" />
				</section>
			)}
		</div>
	)
}
