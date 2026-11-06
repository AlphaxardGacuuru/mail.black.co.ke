import { useEffect, useState } from "react"
import { useEcho, usePresenceChannel } from "@laravel/echo-react"
import { useQueryClient } from "@tanstack/react-query"
import type { ChatMessage } from "@/types/chat"

export type PresenceMember = { id: string; name: string; avatar: string | null }

type Options = {
	onMessage?: (message: ChatMessage) => void
}

// Presence channels double as the transport for ChatMessageSentEvent /
// ChatConversationReadEvent (see routes/channels.php), so joining one channel
// gets a conversation both "who's online" and "what's new" for free.
export function useConversationChannel(
	conversationId: string,
	{ onMessage }: Options = {}
) {
	const queryClient = useQueryClient()
	const [onlineUserIds, setOnlineUserIds] = useState<Set<string>>(new Set())
	const channelName = `chat-conversation.${conversationId}`

	const { channel } = usePresenceChannel(channelName)

	useEcho(
		channelName,
		"ChatMessageSentEvent",
		(event: { message: ChatMessage }) => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
			onMessage?.(event.message)
		},
		[conversationId],
		"presence"
	)

	useEcho(
		channelName,
		"ChatConversationReadEvent",
		() => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
		[conversationId],
		"presence"
	)

	// LocationShareStartedEvent/Stopped only need to refresh otherUserLocationShare
	// / myLocationShare on the conversation — LocationShareMapDialog listens
	// for LocationShareStoppedEvent separately to close itself while open.
	useEcho(
		channelName,
		"LocationShareStartedEvent",
		() => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
		[conversationId],
		"presence"
	)

	useEcho(
		channelName,
		"LocationShareStoppedEvent",
		() => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
		[conversationId],
		"presence"
	)

	useEffect(() => {
		const presenceChannel = channel()

		if (!presenceChannel) {
			return
		}

		presenceChannel
			.here((members: PresenceMember[]) => {
				setOnlineUserIds(new Set(members.map((member) => member.id)))
			})
			.joining((member: PresenceMember) => {
				setOnlineUserIds((previous) => new Set(previous).add(member.id))
			})
			.leaving((member: PresenceMember) => {
				setOnlineUserIds((previous) => {
					const next = new Set(previous)
					next.delete(member.id)
					return next
				})
			})
	}, [conversationId, channel])

	return { onlineUserIds, channel }
}
