import {
	Archive,
	ArchiveRestore,
	ArrowLeft,
	MailCheck,
	MailOpen,
	Star,
	Trash2,
	X,
} from "lucide-react"
import { useMemo, useState } from "react"
import MailComposeInline from "@/components/mail/MailComposeInline"
import MailMessageBubble from "@/components/mail/MailMessageBubble"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import toast from "@/lib/toast"
import {
	useArchiveMailThread,
	useMarkMailThreadRead,
	useMailThread,
	useRestoreMailThread,
	useRetryMailMessage,
	useStarMailThread,
	useTrashMailThread,
} from "@/queries/mail"

type Props = {
	threadId: string | null
	variant: "pane" | "page"
	onClose?: () => void
	onBack?: () => void
}

export default function MailThreadView({
	threadId,
	variant,
	onClose,
	onBack,
}: Props) {
	const { auth } = useApp()
	const activeAccount = auth?.mailgunAccounts?.find(
		(account) => account.isActive
	)
	const { data: thread, isLoading } = useMailThread(threadId)

	const starMutation = useStarMailThread(true)
	const unstarMutation = useStarMailThread(false)
	const archiveMutation = useArchiveMailThread()
	const restoreMutation = useRestoreMailThread()
	const trashMutation = useTrashMailThread()
	const markReadMutation = useMarkMailThreadRead(true)
	const markUnreadMutation = useMarkMailThreadRead(false)
	const retryMutation = useRetryMailMessage(threadId)

	const [expandedId, setExpandedId] = useState<string | null>(null)

	const defaultExpandedId = useMemo(() => {
		if (!thread) {
			return null
		}
		const unread = thread.messages.find((message) => !message.isRead)
		return (unread ?? thread.messages[thread.messages.length - 1])?.id ?? null
	}, [thread])

	const currentlyExpanded = expandedId ?? defaultExpandedId

	if (!threadId) {
		return (
			<div className="flex flex-1 items-center justify-center text-muted-foreground text-sm">
				Select a message to read it
			</div>
		)
	}

	if (isLoading || !thread) {
		return (
			<div className="flex-1 p-4 space-y-3">
				<Skeleton className="h-6 w-2/3" />
				<Skeleton className="h-24 w-full" />
				<Skeleton className="h-24 w-full" />
			</div>
		)
	}

	const lastMessage = thread.messages[thread.messages.length - 1]
	const isArchived =
		thread.messages.length > 0 &&
		thread.messages.every((message) => message.folder === "archive")
	const hasUnread = thread.messages.some((message) => !message.isRead)

	const closeThread = () => {
		if (variant === "pane") {
			onClose?.()
		} else {
			onBack?.()
		}
	}

	const handleArchive = () => {
		const mutation = isArchived ? restoreMutation : archiveMutation
		mutation.mutate(thread.id, {
			onSuccess: () => {
				toast.success(isArchived ? "Restored to original folder" : "Archived")
				closeThread()
			},
		})
	}

	const handleTrash = () => {
		trashMutation.mutate(thread.id, {
			onSuccess: () => {
				toast.success("Moved to trash")
				closeThread()
			},
		})
	}

	const toggleStar = () => {
		const mutation = thread.isStarred ? unstarMutation : starMutation
		mutation.mutate(thread.id)
	}

	const toggleRead = () => {
		const mutation = hasUnread ? markReadMutation : markUnreadMutation
		mutation.mutate(thread.id)
	}

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

				<h2 className="flex-1 truncate font-medium">
					{thread.subject || "(no subject)"}
				</h2>

				<Button
					variant="ghost"
					size="icon"
					onClick={toggleStar}>
					<Star
						className={
							thread.isStarred
								? "size-4 fill-yellow-400 text-yellow-400"
								: "size-4"
						}
					/>
				</Button>

				<Button
					variant="ghost"
					size="icon"
					aria-label={hasUnread ? "Mark as read" : "Mark as unread"}
					title={hasUnread ? "Mark as read" : "Mark as unread"}
					onClick={toggleRead}>
					{hasUnread ? (
						<MailOpen className="size-4" />
					) : (
						<MailCheck className="size-4" />
					)}
				</Button>

				<Button
					variant="ghost"
					size="icon"
					aria-label={isArchived ? "Unarchive" : "Archive"}
					title={isArchived ? "Unarchive" : "Archive"}
					onClick={handleArchive}>
					{isArchived ? (
						<ArchiveRestore className="size-4" />
					) : (
						<Archive className="size-4" />
					)}
				</Button>

				<Button
					variant="ghost"
					size="icon"
					onClick={handleTrash}>
					<Trash2 className="size-4" />
				</Button>

				{variant === "pane" && (
					<Button
						variant="ghost"
						size="icon"
						onClick={onClose}>
						<X className="size-4" />
					</Button>
				)}
			</div>

			<div className="min-h-0 flex-1 overflow-y-auto p-3 space-y-2">
				{thread.messages.map((message) => (
					<MailMessageBubble
						key={message.id}
						message={message}
						isExpanded={currentlyExpanded === message.id}
						onToggleExpand={() =>
							setExpandedId(
								currentlyExpanded === message.id ? null : message.id
							)
						}
						onRetry={() => {
							retryMutation.mutate(message.id, {
								onSuccess: () => toast.success("Message queued for sending"),
								onError: () => toast.error("Couldn't retry sending"),
							})
						}}
						isRetrying={
							retryMutation.isPending && retryMutation.variables === message.id
						}
					/>
				))}
			</div>

			{lastMessage && (
				<div className="bg-card p-3">
					<MailComposeInline
						parentMessage={lastMessage}
						currentUserEmail={activeAccount?.mailboxAddress}
						onSent={() => {}}
					/>
				</div>
			)}
		</div>
	)
}
