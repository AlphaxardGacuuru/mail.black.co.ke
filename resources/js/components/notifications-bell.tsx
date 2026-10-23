import { Bell } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Link } from "@/components/ui/link"
import { useApp } from "@/contexts/AppContext"
import { useNotifications } from "@/hooks/use-notifications"
import { cn } from "@/lib/utils"

/**
 * Header bell: shows how many notifications are unread and opens the full
 * notifications page.
 */
export function NotificationsBell({ className }: { className?: string }) {
	const { auth } = useApp()
	const { unreadCount } = useNotifications()

	if (!auth) {
		return null
	}

	const label =
		unreadCount > 0
			? `Notifications, ${unreadCount} unread`
			: "Notifications"

	return (
		<Link
			href="/notifications"
			variant="unstyled"
			aria-label={label}
			title={label}
			data-test="header-notifications-link"
			className={cn(
				"relative flex size-9 shrink-0 items-center justify-center rounded-full text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
				className
			)}>
			<Bell className="size-5" />
			{unreadCount > 0 && (
				<Badge
					variant="destructive"
					className="absolute -top-0.5 -right-0.5 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] leading-none">
					{unreadCount > 9 ? "9+" : unreadCount}
				</Badge>
			)}
		</Link>
	)
}
