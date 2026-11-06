import { Forward, MoreVertical, Reply, Star, Trash2 } from "lucide-react"
import { useState } from "react"
import ChatAttachmentChip from "@/components/chat/ChatAttachmentChip"
import ChatStatusIcon from "@/components/chat/ChatStatusIcon"
import { Button } from "@/components/ui/button"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useSwipeActions } from "@/hooks/use-swipe-actions"
import { cn } from "@/lib/utils"
import type { ChatMessage } from "@/types/chat"

function formatTime(value: string): string {
	return new Date(value).toLocaleTimeString(undefined, {
		hour: "numeric",
		minute: "2-digit",
	})
}

type Props = {
	message: ChatMessage
	isOwn: boolean
	onReply: (message: ChatMessage) => void
	onToggleStar: (message: ChatMessage) => void
	onForward: (message: ChatMessage) => void
	onDelete: (message: ChatMessage) => void
}

export default function MessageBubble({
	message,
	isOwn,
	onReply,
	onToggleStar,
	onForward,
	onDelete,
}: Props) {
	const [menuOpen, setMenuOpen] = useState(false)

	const { offsetX, progress, bind } = useSwipeActions({
		onSwipeRight: () => onReply(message),
		onSwipeLeft: () => onDelete(message),
		onLongPress: () => setMenuOpen(true),
	})

	return (
		<div
			className={cn(
				"group/bubble relative flex",
				isOwn ? "justify-end" : "justify-start"
			)}>
			<div
				className="pointer-events-none absolute inset-y-0 left-0 flex items-center text-primary transition-opacity"
				style={{ opacity: Math.max(0, progress) }}>
				<Reply className="size-5" />
			</div>
			<div
				className="pointer-events-none absolute inset-y-0 right-0 flex items-center text-destructive transition-opacity"
				style={{ opacity: Math.max(0, -progress) }}>
				<Trash2 className="size-5" />
			</div>

			{/*
				The dropdown trigger/content must NOT be a React descendant of the
				swipeable div below: Radix portals still bubble events through the
				React tree (not the DOM tree), so a click inside the menu would also
				fire the swipe handlers' onPointerDown/setPointerCapture and hijack
				the pointer, silently breaking the click.
			*/}
			<div
				{...bind}
				style={{
					transform: `translateX(${offsetX}px)`,
					WebkitTouchCallout: "none",
				}}
				className={cn(
					"relative max-w-[75%] touch-pan-y space-y-1 rounded-2xl px-3 py-2 text-sm shadow-sm select-none",
					// Only reserved on md+: below that the trigger is invisible
					// (long-press opens the menu instead, see the button below),
					// so keeping the extra padding would just be dead space.
					isOwn ? "md:pr-7" : "md:pl-7",
					offsetX === 0 && "transition-transform duration-200 ease-out",
					isOwn
						? "rounded-br-sm bg-primary/50 text-primary-foreground"
						: "rounded-bl-sm bg-secondary/50 text-foreground"
				)}>
				{message.replyTo && (
					<div
						className={cn(
							"rounded-lg border-l-2 px-2 py-1 text-xs opacity-80",
							isOwn ? "border-primary-foreground/50" : "border-foreground/30"
						)}>
						<p className="truncate">
							{message.replyTo.body ??
								(message.replyTo.hasAttachments ? "Attachment" : "")}
						</p>
					</div>
				)}

				{message.body && (
					<p className="whitespace-pre-wrap wrap-break-word">{message.body}</p>
				)}

				{message.attachments.length > 0 && (
					<div className="flex flex-wrap gap-2">
						{message.attachments.map((attachment) => (
							<ChatAttachmentChip
								key={attachment.id}
								attachment={attachment}
							/>
						))}
					</div>
				)}

				<div
					className={cn(
						"flex items-center justify-end gap-1 text-[11px]",
						isOwn ? "text-primary-foreground/80" : "text-muted-foreground"
					)}>
					{message.isStarred && (
						<Star className="size-3 fill-current text-amber-500" />
					)}
					<span>{formatTime(message.createdAt)}</span>
					{isOwn && (
						<ChatStatusIcon
							isRead={message.isRead}
							className="size-3"
						/>
					)}
				</div>
			</div>

			<DropdownMenu
				open={menuOpen}
				onOpenChange={setMenuOpen}>
				<DropdownMenuTrigger asChild>
					<Button
						type="button"
						variant="ghost"
						size="icon"
						className={cn(
							// Hidden (but still present, so it stays a valid anchor
							// for the menu) below md: small screens open this via
							// long-press on the bubble instead of a visible target.
							"absolute top-0.5 size-5 opacity-0 pointer-events-none md:pointer-events-auto md:opacity-60 md:hover:opacity-100",
							isOwn ? "right-0.5" : "left-0.5"
						)}
						aria-label="Message actions"
						title="Message actions">
						<MoreVertical className="size-3.5" />
					</Button>
				</DropdownMenuTrigger>
				<DropdownMenuContent align={isOwn ? "end" : "start"}>
					<DropdownMenuItem onClick={() => onReply(message)}>
						<Reply /> Reply
					</DropdownMenuItem>
					<DropdownMenuItem onClick={() => onToggleStar(message)}>
						<Star
							className={cn(message.isStarred && "fill-current text-amber-500")}
						/>
						{message.isStarred ? "Unstar" : "Star"}
					</DropdownMenuItem>
					<DropdownMenuItem onClick={() => onForward(message)}>
						<Forward /> Forward
					</DropdownMenuItem>
					<DropdownMenuItem
						variant="destructive"
						onClick={() => onDelete(message)}>
						<Trash2 /> Delete
					</DropdownMenuItem>
				</DropdownMenuContent>
			</DropdownMenu>
		</div>
	)
}
