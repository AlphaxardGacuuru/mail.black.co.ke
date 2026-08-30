import {
	Archive,
	ArchiveRestore,
	MailCheck,
	MailOpen,
	Paperclip,
	Star,
	Trash2,
} from "lucide-react"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { useEffect, useRef, useState } from "react"
import MailStatusIcon from "@/components/mail/MailStatusIcon"
import { cn } from "@/lib/utils"
import {
	useArchiveMailThread,
	useMarkMailThreadRead,
	useRestoreMailThread,
	useStarMailThread,
	useTrashMailThread,
} from "@/queries/mail"
import type { MailThreadSummary } from "@/types/mail"

const HIGHLIGHT_DURATION_MS = 2500

type Props = {
	thread: MailThreadSummary
	folder: string
	isSelected: boolean
	isIncoming?: boolean
	onSelect: () => void
}

function initials(name?: string | null, address?: string | null): string {
	const source = name?.trim() || address?.trim() || "?"
	return source.slice(0, 2).toUpperCase()
}

function formatDate(value: string | null): string {
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

export default function MailThreadListRow({
	thread,
	folder,
	isSelected,
	isIncoming,
	onSelect,
}: Props) {
	const [isHighlighted, setIsHighlighted] = useState(false)
	const starMutation = useStarMailThread(true)
	const unstarMutation = useStarMailThread(false)
	const archiveMutation = useArchiveMailThread()
	const restoreMutation = useRestoreMailThread()
	const trashMutation = useTrashMailThread()
	const markReadMutation = useMarkMailThreadRead(true)
	const markUnreadMutation = useMarkMailThreadRead(false)
	const touchStartRef = useRef<{ x: number; y: number } | null>(null)
	const swipeOffsetRef = useRef(0)
	const swipedRef = useRef(false)
	const [swipeOffset, setSwipeOffset] = useState(0)

	useEffect(() => {
		if (!isIncoming) {
			return
		}

		setIsHighlighted(true)
		const timeout = setTimeout(() => setIsHighlighted(false), HIGHLIGHT_DURATION_MS)

		return () => clearTimeout(timeout)
	}, [isIncoming])

	function handleTouchStart(event: React.TouchEvent<HTMLDivElement>): void {
		if ((event.target as HTMLElement).closest("button")) {
			return
		}

		const touch = event.touches[0]
		touchStartRef.current = { x: touch.clientX, y: touch.clientY }
		swipedRef.current = false
	}

	function handleTouchMove(event: React.TouchEvent<HTMLDivElement>): void {
		if (!touchStartRef.current) {
			return
		}

		const touch = event.touches[0]
		const deltaX = touch.clientX - touchStartRef.current.x
		const deltaY = touch.clientY - touchStartRef.current.y

		if (Math.abs(deltaY) > Math.abs(deltaX)) {
			return
		}

		const offset = Math.max(-120, Math.min(120, deltaX))
		swipeOffsetRef.current = offset
		setSwipeOffset(offset)
	}

	function handleTouchEnd(): void {
		if (!touchStartRef.current) {
			return
		}

		if (swipeOffsetRef.current <= -72) {
			trashMutation.mutate(thread.id)
			swipedRef.current = true
		} else if (swipeOffsetRef.current >= 72) {
			;(folder === "archive" ? restoreMutation : archiveMutation).mutate(
				thread.id
			)
			swipedRef.current = true
		}

		touchStartRef.current = null
		swipeOffsetRef.current = 0
		setSwipeOffset(0)
	}

	return (
		<div
			onClick={() => {
				if (swipedRef.current) {
					swipedRef.current = false
					return
				}

				onSelect()
			}}
			onTouchStart={handleTouchStart}
			onTouchMove={handleTouchMove}
			onTouchEnd={handleTouchEnd}
			onTouchCancel={handleTouchEnd}
			className={cn(
				"group relative overflow-hidden rounded-lg border cursor-pointer shadow-sm transition-shadow duration-700 ease-out hover:shadow-md hover:bg-muted/50",
				"animate-in fade-in slide-in-from-top-2 duration-500",
				isSelected && "bg-muted",
				thread.hasUnread && "border-l-4 border-l-primary bg-primary/5",
				isHighlighted && "border-4 border-primary bg-primary/5"
			)}>
			<div className="absolute inset-y-0 left-0 flex w-24 items-center bg-muted px-4 text-muted-foreground">
				{folder === "archive" ? (
					<ArchiveRestore className="size-5" />
				) : (
					<Archive className="size-5" />
				)}
			</div>
			<div className="absolute inset-y-0 right-0 flex w-24 items-center justify-end bg-destructive px-4 text-destructive-foreground">
				<Trash2 className="size-5" />
			</div>

			<div
				className="relative flex items-center gap-3 bg-background px-3 py-2.5 transition-transform duration-200 ease-out"
				style={{ transform: `translateX(${swipeOffset}px)` }}>
				<Avatar className="size-9 shrink-0">
					<AvatarFallback>
						{initials(thread.from?.name, thread.from?.address)}
					</AvatarFallback>
				</Avatar>

				<div className="flex-1 min-w-0">
					<div className="flex items-center justify-between gap-2">
						{/* Name Start */}
						<span
							className={cn("truncate", thread.hasUnread && "font-semibold")}>
							{thread.from?.name ?? thread.from?.address ?? "Unknown"}
						</span>
						{/* Name End */}
						{/* Timestamp Start */}
						<span className="text-xs text-muted-foreground shrink-0 group-hover:hidden">
							{formatDate(thread.lastMessageAt)}
						</span>
						{/* Timestamp End */}
					</div>

					<div className="flex items-center justify-between gap-2">
						<div>
							{/* Subject Start */}
							<span
								className={cn(
									"truncate text-sm",
									thread.hasUnread ? "font-medium" : "text-muted-foreground"
								)}>
								{thread.subject || "(no subject)"}
								{thread.snippet ? ` — ${thread.snippet}` : ""}
							</span>
							{/* Subject End */}
							{/* Message Count Start */}
							{thread.messageCount > 1 && (
								<span className="text-xs text-muted-foreground shrink-0">
									({thread.messageCount})
								</span>
							)}
							{/* Message Count End */}
							{/* Attachments Start */}
							{thread.hasAttachments && (
								<Paperclip className="size-3 text-muted-foreground shrink-0" />
							)}
							{/* Attachments End */}
						</div>
						{/* Status Start */}
						<div className="group-hover:hidden">
							{thread.status && (
								<MailStatusIcon
									status={thread.status}
									className="size-3.5 shrink-0"
								/>
							)}
						</div>
						{/* Status End */}
					</div>
				</div>

				<div className="hidden items-center gap-0.5 shrink-0 group-hover:flex">
					<Button
						variant="ghost"
						size="icon"
						aria-label={thread.hasUnread ? "Mark as read" : "Mark as unread"}
						title={thread.hasUnread ? "Mark as read" : "Mark as unread"}
						className="size-7"
						onClick={(event) => {
							event.stopPropagation()
							;(thread.hasUnread
								? markReadMutation
								: markUnreadMutation
							).mutate(thread.id)
						}}>
						{thread.hasUnread ? (
							<MailOpen className="size-3.5" />
						) : (
							<MailCheck className="size-3.5" />
						)}
					</Button>
					<Button
						variant="ghost"
						size="icon"
						className="size-7"
						onClick={(event) => {
							event.stopPropagation()
							;(thread.isStarred ? unstarMutation : starMutation).mutate(
								thread.id
							)
						}}>
						<Star
							className={
								thread.isStarred
									? "size-3.5 fill-yellow-400 text-yellow-400"
									: "size-3.5"
							}
						/>
					</Button>
					<Button
						variant="ghost"
						size="icon"
						className="size-7"
						onClick={(event) => {
							event.stopPropagation()
							const mutation =
								folder === "archive" ? restoreMutation : archiveMutation
							mutation.mutate(thread.id)
						}}>
						{folder === "archive" ? (
							<ArchiveRestore className="size-3.5" />
						) : (
							<Archive className="size-3.5" />
						)}
					</Button>
					<Button
						variant="ghost"
						size="icon"
						className="size-7"
						onClick={(event) => {
							event.stopPropagation()
							trashMutation.mutate(thread.id)
						}}>
						<Trash2 className="size-3.5" />
					</Button>
				</div>
			</div>
		</div>
	)
}
