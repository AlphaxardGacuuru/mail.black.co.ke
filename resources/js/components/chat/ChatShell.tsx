import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import ChatEmptyState from "@/components/chat/ChatEmptyState"
import ConversationList from "@/components/chat/ConversationList"
import ConversationView from "@/components/chat/ConversationView"
import { useIsMobile } from "@/hooks/use-mobile"
import type { ChatConversation } from "@/types/chat"

type ChatPane = { type: "none" } | { type: "conversation"; id: string }

type Props = {
	initialConversationId?: string
	archived?: boolean
	conversations?: ChatConversation[]
	isLoading?: boolean
}

export default function ChatShell({
	initialConversationId,
	archived = false,
	conversations = [],
	isLoading = false,
}: Props) {
	const isMobile = useIsMobile()
	const navigate = useNavigate()
	const [pane, setPane] = useState<ChatPane>(
		initialConversationId
			? { type: "conversation", id: initialConversationId }
			: { type: "none" }
	)

	function handleSelect(id: string) {
		if (isMobile) {
			navigate({ to: "/chats/$id/show", params: { id } })
		} else {
			setPane({ type: "conversation", id })
		}
	}

	if (isMobile) {
		return (
			<div className="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden">
				<ConversationList
					selectedId={null}
					onSelect={handleSelect}
					archived={archived}
					conversations={conversations}
					isLoading={isLoading}
				/>
			</div>
		)
	}

	return (
		<div className="flex h-[calc(100vh-6rem)] gap-2">
			<section className="w-1/3 shrink-0 overflow-hidden rounded-lg border bg-card shadow-sm">
				<ConversationList
					selectedId={pane.type === "conversation" ? pane.id : null}
					onSelect={handleSelect}
					archived={archived}
					conversations={conversations}
					isLoading={isLoading}
				/>
			</section>

			<section className="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card shadow-sm">
				{pane.type === "conversation" ? (
					<ConversationView
						conversationId={pane.id}
						variant="pane"
					/>
				) : (
					<ChatEmptyState variant="no-selection" />
				)}
			</section>
		</div>
	)
}
