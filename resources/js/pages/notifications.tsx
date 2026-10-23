import { formatDistanceToNow } from "date-fns"
import { Bell, CheckCheck, Trash2 } from "lucide-react"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import { Spinner } from "@/components/ui/spinner"
import { useNotifications } from "@/hooks/use-notifications"
import { Head } from "@/lib/spa"
import { cn } from "@/lib/utils"
import type { Notification } from "@/types"

/**
 * Every notification, newest first. Opening one marks it read (and follows
 * its link if it has one); all can be marked read at once, and each can be
 * deleted, or all cleared at once.
 */
export default function Notifications() {
	const {
		notifications,
		isLoading,
		unreadCount,
		deletingId,
		markAsRead,
		markAllAsRead,
		remove,
		clearAll,
	} = useNotifications()

	return (
		<>
			<Head title="Notifications" />

			<div className="mx-auto w-full max-w-3xl space-y-6">
				<div className="flex flex-wrap items-start justify-between gap-4">
					<Heading
						title="Notifications"
						description={
							unreadCount > 0
								? `${unreadCount} unread`
								: "You're all caught up"
						}
					/>
					{notifications.length > 0 && (
						<div className="flex flex-wrap gap-2">
							{unreadCount > 0 && (
								<Button
									variant="outline"
									onClick={markAllAsRead}
									className="gap-2">
									<CheckCheck className="size-4" />
									Mark all as read
								</Button>
							)}
						<Button
							variant="outline"
							onClick={clearAll}
							disabled={deletingId === "0"}
							className="gap-2">
							{deletingId === "0" ? (
								<Spinner className="size-4" />
							) : (
								<Trash2 className="size-4" />
							)}
							Clear all
						</Button>
						</div>
					)}
				</div>

				<GlassCard className="overflow-hidden p-0">
					{isLoading && (
						<ul className="divide-y divide-border/60">
							{[1, 2, 3, 4].map((key) => (
								<li
									key={key}
									className="flex gap-3 p-4">
									<Skeleton className="mt-1.5 size-2 rounded-full" />
									<div className="flex-1 space-y-2">
										<Skeleton className="h-4 w-32" />
										<Skeleton className="h-4 w-full" />
									</div>
								</li>
							))}
						</ul>
					)}

					{!isLoading && notifications.length === 0 && (
						<div className="flex flex-col items-center gap-3 px-6 py-16 text-center text-muted-foreground">
							<Bell className="size-8" />
							<p className="text-sm">No notifications</p>
						</div>
					)}

					{notifications.length > 0 && (
						<ul className="divide-y divide-border/60">
							{notifications.map((notification) => (
								<NotificationRow
									key={notification.id}
									notification={notification}
									deleting={deletingId === notification.id}
									onOpen={() => markAsRead(notification.id)}
									onDelete={() => remove(notification.id)}
								/>
							))}
						</ul>
					)}
				</GlassCard>
			</div>
		</>
	)
}

Notifications.layout = {
	breadcrumbs: [{ title: "Notifications", href: "/notifications" }],
}

function NotificationRow({
	notification,
	deleting,
	onOpen,
	onDelete,
}: {
	notification: Notification
	deleting: boolean
	onOpen: () => void
	onDelete: () => void
}) {
	const unread = !notification.readAt

	const content = (
		<>
			<span
				className={cn(
					"mt-1.5 size-2 shrink-0 rounded-full",
					unread && "bg-primary"
				)}
				aria-hidden="true"
			/>
			<span className="flex min-w-0 flex-1 flex-col gap-0.5">
				{notification.from && (
					<span className="truncate text-sm font-medium">
						{notification.from}
					</span>
				)}
				<span
					className={cn(
						"text-sm",
						unread ? "text-foreground" : "text-muted-foreground"
					)}>
					{notification.message ?? "New notification"}
				</span>
				<span className="text-xs text-muted-foreground">
					{formatDistanceToNow(new Date(notification.createdAt), {
						addSuffix: true,
					})}
					{unread && <span className="sr-only"> (unread)</span>}
				</span>
			</span>
		</>
	)

	const rowClass =
		"flex min-w-0 flex-1 items-start gap-3 rounded-lg p-2 text-left transition-colors hover:bg-muted/60"

	return (
		<li className={cn("flex items-start gap-2 p-2 sm:p-3", deleting && "opacity-50")}>
			{notification.url ? (
				<Link
					variant="unstyled"
					href={notification.url}
					onClick={onOpen}
					className={rowClass}>
					{content}
				</Link>
			) : (
				<button
					type="button"
					onClick={onOpen}
					className={rowClass}>
					{content}
				</button>
			)}
			<button
				type="button"
				onClick={onDelete}
				disabled={deleting}
				aria-label="Delete notification"
				className="mt-1 shrink-0 rounded-md p-2 text-destructive/80 transition-colors hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-50">
				{deleting ? (
					<Spinner className="size-4" />
				) : (
					<Trash2 className="size-4" />
				)}
			</button>
		</li>
	)
}
