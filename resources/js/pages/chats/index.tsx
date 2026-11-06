import ChatShell from "@/components/chat/ChatShell"
import { Head } from "@/lib/spa"
import { useConversations } from "@/queries/chat"

export default function ChatIndex() {
	const { data: conversations = [], isLoading } = useConversations(false)

	return (
		<>
			<Head title="Chat" />
			<ChatShell
				conversations={conversations}
				isLoading={isLoading}
			/>
		</>
	)
}
