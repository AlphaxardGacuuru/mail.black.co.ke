import { Archive, Paperclip, Star, Trash2 } from "lucide-react"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import MailStatusIcon from "@/components/mail/MailStatusIcon"
import { cn } from "@/lib/utils"
import { useArchiveMailThread, useStarMailThread, useTrashMailThread } from "@/queries/mail"
import type { MailThreadSummary } from "@/types/mail"

type Props = {
	thread: MailThreadSummary
	isSelected: boolean
	onSelect: () => void
}

function initials(name?: string | null, address?: string | null): string {
	const source = name?.trim() || address?.trim() || "?"
	return source.slice(0, 2).toUpperCase()
}

function formatDate(value: string | null): string {
	if (!value) return ""
	const date = new Date(value)
	const now = new Date()
	if (date.toDateString() === now.toDateString()) {
		return date.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })
	}
	return date.toLocaleDateString(undefined, { month: "short", day: "numeric" })
}

export default function MailThreadListRow({ thread, isSelected, onSelect }: Props) {
	const starMutation = useStarMailThread(true)
	const unstarMutation = useStarMailThread(false)
	const archiveMutation = useArchiveMailThread()
	const trashMutation = useTrashMailThread()

	return (
		<div
			onClick={onSelect}
			className={cn(
				"group flex items-center gap-3 border-b px-3 py-2.5 cursor-pointer hover:bg-muted/50 transition-colors",
				isSelected && "bg-muted",
				thread.hasUnread && "bg-background"
			)}>
			<div className="relative shrink-0">
				<Avatar className="size-9">
					<AvatarFallback>{initials(thread.from?.name, thread.from?.address)}</AvatarFallback>
				</Avatar>
				{thread.status && (
					<span className="absolute -bottom-0.5 -left-0.5 flex items-center justify-center rounded-full bg-background p-0.5 ring-1 ring-background">
						<MailStatusIcon
							status={thread.status}
							isRead={thread.isRead}
							className="size-2.5"
						/>
					</span>
				)}
			</div>

			<div className="flex-1 min-w-0">
				<div className="flex items-center justify-between gap-2">
					<span className={cn("truncate", thread.hasUnread && "font-semibold")}>
						{thread.from?.name ?? thread.from?.address ?? "Unknown"}
					</span>
					<span className="text-xs text-muted-foreground shrink-0">
						{formatDate(thread.lastMessageAt)}
					</span>
				</div>

				<div className="flex items-center gap-2">
					<span className={cn("truncate text-sm", thread.hasUnread ? "font-medium" : "text-muted-foreground")}>
						{thread.subject || "(no subject)"}
						{thread.snippet ? ` — ${thread.snippet}` : ""}
					</span>
					{thread.messageCount > 1 && (
						<span className="text-xs text-muted-foreground shrink-0">({thread.messageCount})</span>
					)}
					{thread.hasAttachments && <Paperclip className="size-3 text-muted-foreground shrink-0" />}
				</div>
			</div>

			<div className="hidden group-hover:flex items-center gap-0.5 shrink-0">
				<Button
					variant="ghost"
					size="icon"
					className="size-7"
					onClick={(event) => {
						event.stopPropagation()
						;(thread.isStarred ? unstarMutation : starMutation).mutate(thread.id)
					}}>
					<Star className={thread.isStarred ? "size-3.5 fill-yellow-400 text-yellow-400" : "size-3.5"} />
				</Button>
				<Button
					variant="ghost"
					size="icon"
					className="size-7"
					onClick={(event) => {
						event.stopPropagation()
						archiveMutation.mutate(thread.id)
					}}>
					<Archive className="size-3.5" />
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
	)
}
