import { useNavigate } from "@tanstack/react-router"
import { useState } from "react"
import ConversationView from "@/components/chat/ConversationView"
import { Head } from "@/lib/spa"

export default function ChatShow({ id }: { id: string }) {
	const navigate = useNavigate()

	// A notification's "Reply" action falls back to this when it couldn't
	// send the typed reply directly (see resources/js/sw.ts) — prefill the
	// composer with it instead of losing what the user typed, then drop it
	// from the address bar so a refresh doesn't resurrect it.
	const [initialBody] = useState(() => {
		const params = new URLSearchParams(window.location.search)
		const draft = params.get("draft") ?? undefined

		if (draft) {
			params.delete("draft")
			const query = params.toString()
			window.history.replaceState(
				null,
				"",
				query
					? `${window.location.pathname}?${query}`
					: window.location.pathname
			)
		}

		return draft
	})

	return (
		<>
			<Head title="Chat" />

			<div className="flex h-[calc(100vh-4rem)] flex-col rounded-lg overflow-hidden">
				<ConversationView
					conversationId={id}
					variant="page"
					onBack={() => navigate({ to: "/chats" })}
					initialBody={initialBody}
				/>
			</div>
		</>
	)
}
