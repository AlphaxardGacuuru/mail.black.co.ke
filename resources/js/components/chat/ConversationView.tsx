import { useEffect, useRef, useState } from "react"
import { ArrowLeft, MapPin } from "lucide-react"
import ForwardMessageDialog from "@/components/chat/ForwardMessageDialog"
import LocationShareMapDialog from "@/components/chat/LocationShareMapDialog"
import MessageBubble from "@/components/chat/MessageBubble"
import MessageComposer from "@/components/chat/MessageComposer"
import TypingIndicator from "@/components/chat/TypingIndicator"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import VerifiedBadge from "@/components/verified-badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import { useConversationChannel } from "@/hooks/use-conversation-channel"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import {
	useConversation,
	useDeleteMessage,
	useMarkConversationRead,
	useToggleStarMessage,
} from "@/queries/chat"
import type { ChatMessage } from "@/types/chat"

const UNDO_DELETE_WINDOW_MS = 5000

const TYPING_WHISPER_THROTTLE_MS = 2000
const TYPING_IDLE_TIMEOUT_MS = 3000

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

function formatLastSeen(value: string): string {
	const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60_000)

	if (minutes < 1) {
		return "just now"
	}
	if (minutes < 60) {
		return `${minutes}m ago`
	}

	const hours = Math.floor(minutes / 60)
	if (hours < 24) {
		return `${hours}h ago`
	}

	return `${Math.floor(hours / 24)}d ago`
}

type Props = {
	conversationId: string
	variant: "pane" | "page"
	onBack?: () => void
	initialBody?: string
}

export default function ConversationView({
	conversationId,
	variant,
	onBack,
	initialBody,
}: Props) {
	const { auth } = useApp()
	const { data, isLoading } = useConversation(conversationId)
	const markRead = useMarkConversationRead()
	const deleteMessage = useDeleteMessage(conversationId)
	const toggleStarMessage = useToggleStarMessage(conversationId)

	const [isOtherTyping, setIsOtherTyping] = useState(false)
	const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)
	const lastWhisperAtRef = useRef(0)

	const [replyingTo, setReplyingTo] = useState<ChatMessage | null>(null)
	const [forwardingMessage, setForwardingMessage] =
		useState<ChatMessage | null>(null)
	const [locationDialogOpen, setLocationDialogOpen] = useState(false)
	const [pendingDeleteIds, setPendingDeleteIds] = useState<Set<string>>(
		new Set()
	)
	const deleteTimersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(
		new Map()
	)

	const messagesContainerRef = useRef<HTMLDivElement>(null)
	const composerRef = useRef<HTMLDivElement>(null)
	const [composerHeight, setComposerHeight] = useState(0)

	const myId = auth ? String(auth.id) : null

	useEffect(() => {
		const composerEl = composerRef.current
		if (!composerEl) {
			return
		}

		const observer = new ResizeObserver(([entry]) => {
			if (entry) {
				setComposerHeight(entry.contentRect.height)
			}
		})
		observer.observe(composerEl)

		return () => observer.disconnect()
	}, [])

	useEffect(() => {
		const timers = deleteTimersRef.current
		return () => {
			timers.forEach((timeoutId) => clearTimeout(timeoutId))
			timers.clear()
		}
	}, [])

	function cancelPendingDelete(messageId: string) {
		const timeoutId = deleteTimersRef.current.get(messageId)
		if (timeoutId) {
			clearTimeout(timeoutId)
			deleteTimersRef.current.delete(messageId)
		}
		setPendingDeleteIds((previous) => {
			const next = new Set(previous)
			next.delete(messageId)
			return next
		})
	}

	function handleDeleteRequest(message: ChatMessage) {
		if (pendingDeleteIds.has(message.id)) {
			return
		}

		setPendingDeleteIds((previous) => new Set(previous).add(message.id))

		const timeoutId = setTimeout(() => {
			deleteTimersRef.current.delete(message.id)
			deleteMessage.mutate(message.id, {
				onError: () => {
					toast.error("Couldn't delete the message")
					cancelPendingDelete(message.id)
				},
			})
		}, UNDO_DELETE_WINDOW_MS)

		deleteTimersRef.current.set(message.id, timeoutId)

		toast("Message deleted, undo?", {
			duration: UNDO_DELETE_WINDOW_MS,
			action: {
				label: "Undo",
				onClick: () => cancelPendingDelete(message.id),
			},
		})
	}

	function handleToggleStar(message: ChatMessage) {
		toggleStarMessage.mutate(message.id, {
			onError: () => toast.error("Couldn't update the message"),
		})
	}

	const { onlineUserIds, channel } = useConversationChannel(conversationId, {
		onMessage: (message) => {
			if (message.senderId !== myId) {
				markRead.mutate(conversationId)
			}
		},
	})

	useEffect(() => {
		markRead.mutate(conversationId)
	}, [conversationId])

	useEffect(() => {
		const presenceChannel = channel()

		if (!presenceChannel) {
			return
		}

		presenceChannel.listenForWhisper(
			"typing",
			(payload: { userId: string }) => {
				if (payload.userId === myId) {
					return
				}

				setIsOtherTyping(true)

				if (typingTimeoutRef.current) {
					clearTimeout(typingTimeoutRef.current)
				}
				typingTimeoutRef.current = setTimeout(
					() => setIsOtherTyping(false),
					TYPING_IDLE_TIMEOUT_MS
				)
			}
		)

		return () => {
			if (typingTimeoutRef.current) {
				clearTimeout(typingTimeoutRef.current)
			}
		}
	}, [conversationId, myId, channel])

	function sendTypingWhisper() {
		const now = Date.now()
		if (now - lastWhisperAtRef.current < TYPING_WHISPER_THROTTLE_MS) {
			return
		}
		lastWhisperAtRef.current = now
		channel()?.whisper("typing", { userId: myId })
	}

	useEffect(() => {
		const messagesEl = messagesContainerRef.current
		if (messagesEl) {
			messagesEl.scrollTop = messagesEl.scrollHeight
		}
	}, [data?.messages.length])

	if (isLoading || !data) {
		return (
			<div className="flex-1 space-y-3 p-4">
				<Skeleton className="h-6 w-2/3" />
				<Skeleton className="h-24 w-full" />
				<Skeleton className="h-24 w-full" />
			</div>
		)
	}

	const { conversation, messages } = data
	const otherUser = conversation.otherUser
	const isOnline = otherUser ? onlineUserIds.has(otherUser.id) : false

	return (
		<div className="relative flex flex-1 flex-col overflow-hidden">
			<div
				className={cn(
					"sticky top-0 z-30 flex items-center gap-2 p-3 lg:m-3",
					"rounded-xl border border-white/40 bg-white/34 px-4 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20"
				)}>
				{variant === "page" && (
					<Button
						variant="ghost"
						size="icon"
						onClick={onBack}>
						<ArrowLeft className="size-4" />
					</Button>
				)}

				<Avatar className="size-10 shrink-0">
					<AvatarImage
						src={otherUser?.avatar ?? undefined}
						alt={otherUser?.name}
					/>
					<AvatarFallback>{initials(otherUser?.name)}</AvatarFallback>
				</Avatar>

				<div className="min-w-0 flex-1">
					<p className="flex min-w-0 items-center gap-1 font-medium">
						<span className="min-w-0 truncate">
							{otherUser?.name ?? "Unknown"}
						</span>
						{otherUser?.verified && (
							<VerifiedBadge className="size-3.5 shrink-0" />
						)}
						{conversation.otherUserLocationShare && (
							<button
								type="button"
								aria-label="View live location"
								title="View live location"
								className="shrink-0"
								onClick={() => setLocationDialogOpen(true)}>
								<MapPin className="size-3.5 animate-pulse text-primary" />
							</button>
						)}
						{isOnline && (
							<span
								className="size-2 shrink-0 rounded-full bg-primary"
								aria-label="Online"
							/>
						)}
					</p>
					<p className="truncate text-xs text-muted-foreground">
						{isOtherTyping
							? "typing…"
							: isOnline
								? "Online"
								: otherUser?.lastSeenAt
									? `Last seen ${formatLastSeen(otherUser.lastSeenAt)}`
									: ""}
					</p>
				</div>
			</div>

			<div
				ref={messagesContainerRef}
				className="min-h-0 flex-1 space-y-2 overflow-y-auto py-3 lg:mx-3"
				style={{ paddingBottom: composerHeight + 16 }}>
				{messages
					.filter((message) => !pendingDeleteIds.has(message.id))
					.map((message) => (
						<MessageBubble
							key={message.id}
							message={message}
							isOwn={message.senderId === myId}
							onReply={setReplyingTo}
							onToggleStar={handleToggleStar}
							onForward={setForwardingMessage}
							onDelete={handleDeleteRequest}
						/>
					))}

				{isOtherTyping && otherUser && (
					<TypingIndicator name={otherUser.name} />
				)}
			</div>

			<MessageComposer
				ref={composerRef}
				conversationId={conversationId}
				onTyping={sendTypingWhisper}
				replyingTo={replyingTo}
				onCancelReply={() => setReplyingTo(null)}
				initialBody={initialBody}
				myLocationShare={conversation.myLocationShare}
				channel={channel}
			/>

			<ForwardMessageDialog
				message={forwardingMessage}
				excludeConversationId={conversationId}
				onOpenChange={(open) => !open && setForwardingMessage(null)}
			/>

			<LocationShareMapDialog
				open={locationDialogOpen && !!conversation.otherUserLocationShare}
				onOpenChange={setLocationDialogOpen}
				conversationId={conversationId}
				share={conversation.otherUserLocationShare}
				senderName={otherUser?.name ?? "They"}
				channel={channel}
			/>
		</div>
	)
}
