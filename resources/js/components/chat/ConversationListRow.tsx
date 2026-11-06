import { Archive, ArchiveRestore, Trash2 } from "lucide-react"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import VerifiedBadge from "@/components/verified-badge"
import { useConversationChannel } from "@/hooks/use-conversation-channel"
import { useSwipeActions } from "@/hooks/use-swipe-actions"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import { useToggleArchiveConversation } from "@/queries/chat"
import type { ChatConversation } from "@/types/chat"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

function formatTime(value: string | null): string {
	if (!value) {
		return ""
	}

	const date = new Date(value)
	const now = new Date()

	if (date.toDateString() === now.toDateString()) {
		return date.toLocaleTimeString(undefined, {
			hour: "numeric",
			minute: "2-digit",
		})
	}

	return date.toLocaleDateString(undefined, { month: "short", day: "numeric" })
}

type Props = {
	conversation: ChatConversation
	isSelected: boolean
	onSelect: () => void
	onRemove: () => void
	archived?: boolean
}

export default function ConversationListRow({
	conversation,
	isSelected,
	onSelect,
	onRemove,
	archived = false,
}: Props) {
	const { otherUser, lastMessage, unreadCount, lastMessageAt } = conversation

	const { onlineUserIds } = useConversationChannel(conversation.id)
	const isOnline = otherUser ? onlineUserIds.has(otherUser.id) : false

	const toggleArchive = useToggleArchiveConversation()

	const { offsetX, progress, consumeSwipeSuppression, bind } = useSwipeActions({
		onSwipeRight: () => {
			toggleArchive.mutate(conversation.id, {
				onSuccess: ({ isArchived }) =>
					toast.success(
						isArchived ? "Conversation archived" : "Conversation unarchived"
					),
				onError: () => toast.error("Couldn't archive the conversation"),
			})
		},
		onSwipeLeft: onRemove,
	})

	const SwipeRightIcon = archived ? ArchiveRestore : Archive

	return (
		<div className="relative">
			<div
				className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-emerald-600 transition-opacity"
				style={{ opacity: Math.max(0, progress) }}>
				<SwipeRightIcon className="size-5" />
			</div>
			<div
				className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-destructive transition-opacity"
				style={{ opacity: Math.max(0, -progress) }}>
				<Trash2 className="size-5" />
			</div>

			<button
				type="button"
				onClick={() => {
					if (consumeSwipeSuppression()) {
						return
					}
					onSelect()
				}}
				{...bind}
				style={{ transform: `translateX(${offsetX}px)` }}
				className={cn(
					"relative flex w-full touch-pan-y items-center gap-2 rounded-lg border bg-card px-2 py-2 text-left shadow-sm select-none md:gap-3 md:rounded-xl md:px-3 md:py-3",
					offsetX === 0 &&
						"transition-[transform,box-shadow,translate] duration-200 ease-out hover:-translate-y-px hover:shadow-md",
					isSelected && "border-primary/50"
				)}>
				<Avatar className="size-14 shrink-0">
					<AvatarImage
						src={otherUser?.avatar ?? undefined}
						alt={otherUser?.name}
					/>
					<AvatarFallback>{initials(otherUser?.name)}</AvatarFallback>
				</Avatar>

				<div className="min-w-0 flex-1">
					<div className="flex items-center justify-between gap-2">
						<span className="flex min-w-0 items-center gap-1">
							<span
								className={cn(
									"truncate",
									unreadCount > 0 && "font-semibold text-primary"
								)}>
								{otherUser?.name ?? "Unknown"}
							</span>
							{otherUser?.verified && (
								<VerifiedBadge className="size-3.5 shrink-0" />
							)}
							{isOnline && (
								<span
									className="size-2 shrink-0 rounded-full bg-primary"
									aria-label="Online"
								/>
							)}
						</span>
						<span className="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
							{conversation.isArchived && (
								<Archive
									className="size-3"
									aria-label="Archived"
								/>
							)}
							{formatTime(lastMessageAt)}
						</span>
					</div>
					{otherUser?.email && (
						<span className="block truncate text-xs text-muted-foreground">
							{otherUser.email}
						</span>
					)}
					<div className="flex items-center justify-between gap-2">
						<span
							className={cn(
								"truncate text-sm",
								unreadCount > 0
									? "font-medium text-primary"
									: "text-muted-foreground"
							)}>
							{lastMessage?.body || "No messages yet"}
						</span>
						{unreadCount > 0 && (
							<span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground">
								{unreadCount > 9 ? "9+" : unreadCount}
							</span>
						)}
					</div>
				</div>
			</button>
		</div>
	)
}
