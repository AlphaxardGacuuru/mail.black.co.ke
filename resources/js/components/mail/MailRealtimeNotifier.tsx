import { useEcho } from "@laravel/echo-react"
import { useNavigate, useRouterState } from "@tanstack/react-router"
import { useApp } from "@/contexts/AppContext"
import { useMailRealtimeSync } from "@/hooks/use-mail-realtime-sync"
import { playIncomingMailChime } from "@/lib/notification-sound"
import toast from "@/lib/toast"

type MailRealtimeEvent = {
	threadId: string
}

/**
 * Mounted app-wide (in AppSidebar) so new mail sound/toasts fire from any page, not just the mail views.
 * Cache sync itself is handled by useMailRealtimeSync, which also runs directly inside mail views so
 * status updates still land even when this (sidebar-scoped) component isn't mounted, e.g. the mobile
 * sidebar Sheet being closed.
 */
export default function MailRealtimeNotifier() {
	const { auth } = useApp()
	const navigate = useNavigate()
	const pathname = useRouterState({
		select: (state) => state.location.pathname,
	})
	const onMailPages = pathname.startsWith("/mail")

	useMailRealtimeSync()

	useEcho(
		`mail.${auth?.id ?? ""}`,
		"MailMessageReceivedEvent",
		(event: MailRealtimeEvent) => {
			playIncomingMailChime()

			// The mail pages already show an in-context banner, avoid a redundant toast there.
			if (!onMailPages) {
				toast.info("New message received", {
					description: "You have a new email waiting in your inbox.",
					action: {
						label: "View Inbox",
						onClick: () => navigate({ to: "/mail" }),
					},
				})
			}
		}
	)

	return null
}
