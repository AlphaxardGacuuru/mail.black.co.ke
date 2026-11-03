import { useEcho } from "@laravel/echo-react"
import { useQueryClient } from "@tanstack/react-query"
import { useApp } from "@/contexts/AppContext"
import { invalidateThreadLists } from "@/queries/mail"

type MailRealtimeEvent = {
	threadId: string
}

/**
 * Keeps thread list / thread detail query caches in sync with Mailgun-driven
 * status changes and inbound mail, wherever a mail view happens to be
 * mounted (pane, full page, mobile or desktop) — independent of whether
 * MailShell or the app-wide notifier are mounted.
 */
export function useMailRealtimeSync() {
	const { auth } = useApp()
	const queryClient = useQueryClient()

	function syncThread(event: MailRealtimeEvent) {
		invalidateThreadLists(queryClient)
		queryClient.invalidateQueries({
			queryKey: ["mail", "thread", event.threadId],
		})
	}

	useEcho(`mail.${auth?.id ?? ""}`, "MailMessageStatusUpdatedEvent", syncThread)
	useEcho(`mail.${auth?.id ?? ""}`, "MailMessageReceivedEvent", syncThread)
}
