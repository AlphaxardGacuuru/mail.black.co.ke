import { useEcho } from "@laravel/echo-react"
import { useQueryClient } from "@tanstack/react-query"
import { useRouterState } from "@tanstack/react-router"
import { useApp } from "@/contexts/AppContext"
import { playIncomingMailChime } from "@/lib/notification-sound"
import toast from "@/lib/toast"

type MailRealtimeEvent = {
	threadId: string
}

/**
 * Mounted app-wide (in AppSidebar) so new mail sound/toasts fire from any page, not just the mail views.
 */
export default function MailRealtimeNotifier() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const pathname = useRouterState({
		select: (state) => state.location.pathname,
	})
	const onMailPages = pathname.startsWith("/mail")

	useEcho(
		`mail.${auth?.id ?? ""}`,
		"MailMessageStatusUpdatedEvent",
		(event: MailRealtimeEvent) => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
			queryClient.invalidateQueries({
				queryKey: ["mail", "thread", event.threadId],
			})
		}
	)

	useEcho(
		`mail.${auth?.id ?? ""}`,
		"MailMessageReceivedEvent",
		(event: MailRealtimeEvent) => {
			playIncomingMailChime()
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
			queryClient.invalidateQueries({
				queryKey: ["mail", "thread", event.threadId],
			})

			// The mail pages already show an in-context banner, avoid a redundant toast there.
			if (!onMailPages) {
				toast.info("New message received", {
					description: "You have a new email waiting in your inbox.",
				})
			}
		}
	)

	return null
}
