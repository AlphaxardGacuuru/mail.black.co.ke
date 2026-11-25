import { Head } from "@/lib/spa"
import ChatShell from "@/components/chat/ChatShell"

export default function ChatArchived() {
	return (
		<>
			<Head title="Chat" />
			<ChatShell archived />
		</>
	)
}
