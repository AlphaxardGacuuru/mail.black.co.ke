import { useQuery, useQueryClient } from "@tanstack/react-query"
import { formatDistanceToNow } from "date-fns"
import { Bell, Trash2 } from "lucide-react"
import { useEffect } from "react"
import type { MouseEvent } from "react"
import { useEchoModel } from "@laravel/echo-react"
import { Link } from "@/components/ui/link"
import { Badge } from "@/components/ui/badge"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { useApp } from "@/contexts/AppContext"
import { useIsMobile } from "@/hooks/use-mobile"
import Axios from "@/lib/axios"
import {
	index as indexRoute,
	update as updateRoute,
	destroy as destroyRoute,
} from "@/routes/notifications"
import type { Notification } from "@/types"

export function NavNotifications() {
	const { auth } = useApp()
	const { state } = useSidebar()
	const isMobile = useIsMobile()
	const queryClient = useQueryClient()
	const { channel } = useEchoModel("App.Models.User", auth?.id ?? 0)

	const { data: notifications = [] } = useQuery<Notification[]>({
		queryKey: ["notifications"],
		queryFn: () =>
			Axios.get(indexRoute.url()).then((response) => response.data.data),
		enabled: !!auth,
	})

	useEffect(() => {
		channel()?.notification(() => {
			queryClient.invalidateQueries({ queryKey: ["notifications"] })
		})
	}, [auth?.id, channel, queryClient])

	const unreadCount = notifications.filter(
		(notification) => !notification.readAt
	).length

	const markAsRead = (id: string) => {
		Axios.put(updateRoute.url(id)).finally(() => {
			queryClient.invalidateQueries({ queryKey: ["notifications"] })
		})
	}

	const onDeleteNotifications = (id: string) => {
		// Clear the notifications array
		queryClient.invalidateQueries({ queryKey: ["notifications"] })

		Axios.delete(destroyRoute.url(id)).then(() => {
			queryClient.invalidateQueries({ queryKey: ["notifications"] })
		})
	}

	const handleDeleteNotification = (
		event: MouseEvent<HTMLButtonElement>,
		id: string
	) => {
		event.preventDefault()
		event.stopPropagation()
		onDeleteNotifications(id)
	}

	const handleClearAll = (event: MouseEvent<HTMLButtonElement>) => {
		event.preventDefault()
		event.stopPropagation()
		onDeleteNotifications("0")
	}

	return (
		<SidebarMenu>
			<SidebarMenuItem>
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<SidebarMenuButton
							size="lg"
							className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
							data-test="sidebar-notifications-button">
							<div className="relative flex size-8 items-center justify-center">
								<Bell className="size-5" />
								{unreadCount > 0 && (
									<Badge
										variant="destructive"
										className="absolute -top-1 -right-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] leading-none">
										{unreadCount > 9 ? "9+" : unreadCount}
									</Badge>
								)}
							</div>
							<span className="truncate">Notifications</span>
						</SidebarMenuButton>
					</DropdownMenuTrigger>
					<DropdownMenuContent
						className="w-(--radix-dropdown-menu-trigger-width) min-w-80 rounded-lg"
						align="end"
						side={isMobile ? "bottom" : state === "collapsed" ? "left" : "top"}
						collisionPadding={16}>
						<DropdownMenuLabel className="flex items-center justify-between gap-2">
							<span>Notifications</span>
							{notifications.length > 0 && (
								<button
									type="button"
									onClick={handleClearAll}
									className="text-xs font-normal text-muted-foreground hover:text-foreground cursor-pointer">
									Clear all
								</button>
							)}
						</DropdownMenuLabel>
						<DropdownMenuSeparator />
						{notifications.length === 0 ? (
							<div className="px-2 py-4 text-center text-sm text-muted-foreground">
								No notifications
							</div>
						) : (
							<div className="max-h-80 overflow-y-auto">
								{notifications.map((notification) => {
									const content = (
										<>
											<span
												className={
													notification.readAt
														? "mt-1.5 size-2 shrink-0 rounded-full"
														: "mt-1.5 size-2 shrink-0 rounded-full bg-primary"
												}
											/>
											<span className="flex min-w-0 flex-1 flex-col gap-0.5">
												{notification.from && (
													<span className="truncate text-sm font-medium">
														{notification.from}
													</span>
												)}
												<span className="line-clamp-2 text-sm text-muted-foreground">
													{notification.message ?? "New notification"}
												</span>
												<span className="text-xs text-muted-foreground">
													{formatDistanceToNow(
														new Date(notification.createdAt),
														{
															addSuffix: true,
														}
													)}
												</span>
											</span>
										</>
									)

									return (
										<DropdownMenuItem
											key={notification.id}
											className="items-start gap-2 py-2">
											<div className="flex w-full items-start justify-between gap-2">
												{notification.url ? (
													<Link
														variant="unstyled"
														className="flex min-w-0 flex-1 items-start gap-2"
														href={notification.url}
														onClick={() => markAsRead(notification.id)}>
														{content}
													</Link>
												) : (
													<button
														type="button"
														onClick={() => markAsRead(notification.id)}
														className="flex min-w-0 flex-1 items-start gap-2 text-left">
														{content}
													</button>
												)}
												<button
													type="button"
													onClick={(event) =>
														handleDeleteNotification(event, notification.id)
													}
													className="shrink-0 self-center rounded-sm p-1 text-destructive/80 hover:bg-destructive/10 hover:text-destructive cursor-pointer"
													aria-label="Delete notification">
													<Trash2 className="size-4 shrink-0" />
												</button>
											</div>
										</DropdownMenuItem>
									)
								})}
							</div>
						)}
					</DropdownMenuContent>
				</DropdownMenu>
			</SidebarMenuItem>
		</SidebarMenu>
	)
}
