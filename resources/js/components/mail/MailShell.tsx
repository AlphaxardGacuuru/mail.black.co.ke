import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import MailComposePane from "@/components/mail/MailComposePane"
import MailEmptyState from "@/components/mail/MailEmptyState"
import MailThreadList from "@/components/mail/MailThreadList"
import MailThreadView from "@/components/mail/MailThreadView"
import { useIsMobile } from "@/hooks/use-mobile"
import { useLabels } from "@/queries/mail"
import type { MailThreadFilters } from "@/queries/mail"
import type { MailFolderKey } from "@/types/mail"

type MailPane = { type: "none" } | { type: "thread"; id: string } | { type: "compose" }

type Props = {
	folder: MailFolderKey
	labelId?: string
	initialPane?: MailPane
}

export default function MailShell({ folder, labelId, initialPane }: Props) {
	const isMobile = useIsMobile()
	const navigate = useNavigate()
	const { data: labels } = useLabels()

	const [filters, setFilters] = useState<MailThreadFilters>({
		folder,
		label: labelId,
		page: 1,
		q: "",
	})
	const [pane, setPane] = useState<MailPane>(initialPane ?? { type: "none" })

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

	const activeLabel = labelId ? labels?.find((label) => label.id === labelId) : undefined
	const title = labelId ? (activeLabel?.name ?? "Label") : folder

	if (isMobile) {
		return (
			<div className="flex h-[calc(100vh-4rem)] flex-col">
				<MailThreadList
					filters={filters}
					onFiltersChange={setFilters}
					selectedThreadId={null}
					onSelectThread={handleSelectThread}
					onCompose={openCompose}
					title={title}
				/>
			</div>
		)
	}

	return (
		<div className="flex h-[calc(100vh-6rem)] gap-4">
			<section className="w-1/4 shrink-0 overflow-hidden rounded-lg border bg-card shadow-sm">
				<MailThreadList
					filters={filters}
					onFiltersChange={setFilters}
					selectedThreadId={pane.type === "thread" ? pane.id : null}
					onSelectThread={handleSelectThread}
					onCompose={openCompose}
					title={title}
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
						onSent={closePane}
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
