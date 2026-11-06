import { Head } from "@/lib/spa"
import ChatShell from "@/components/chat/ChatShell"
import { useConversations } from "@/queries/chat"

export default function ChatArchived() {
	const { data: conversations = [], isLoading } = useConversations(true)

	return (
		<>
			<Head title="Chat" />
			<ChatShell
				archived
				conversations={conversations}
				isLoading={isLoading}
			/>
		</>
	)
}
