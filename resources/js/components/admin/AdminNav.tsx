import {
	Activity,
	ChevronRight,
	Coins,
	Gauge,
	LayoutDashboard,
	ShieldCheck,
	Telescope,
	Users,
	Webhook,
} from "lucide-react"
import { Link } from "@/components/ui/link"
import {
	Collapsible,
	CollapsibleContent,
	CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
	SidebarGroup,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	SidebarMenuSub,
	SidebarMenuSubButton,
	SidebarMenuSubItem,
} from "@/components/ui/sidebar"
import { useApp } from "@/contexts/AppContext"
import { useCurrentUrl } from "@/hooks/use-current-url"
import type { User } from "@/types"

type AdminItem = {
	title: string
	href: string
	icon: typeof Coins
	newTab?: boolean
	/** Shown only when the signed-in user may see it. */
	visible?: (auth: User | undefined) => boolean
}

// Telescope, Horizon and Pulse are Laravel-rendered dashboards, opened in a
// new tab so the app stays open behind them.
const ADMIN_ITEMS: AdminItem[] = [
	{ title: "Dashboard", href: "/admin/dashboard", icon: LayoutDashboard },
	{ title: "Webhooks", href: "/admin/webhooks", icon: Webhook },
	{ title: "Users", href: "/admin/users", icon: Users },
	{
		title: "AI usage",
		href: "/admin/ai-usage",
		icon: Coins,
		visible: (auth) => Boolean(auth?.can_view_ai_usage),
	},
	{ title: "Telescope", href: "/telescope", icon: Telescope, newTab: true },
	{ title: "Horizon", href: "/horizon", icon: Activity, newTab: true },
	{ title: "Pulse", href: "/pulse", icon: Gauge, newTab: true },
]

export function AdminNav() {
	const { auth } = useApp()
	const { isCurrentOrParentUrl } = useCurrentUrl()
	const items = ADMIN_ITEMS.filter((item) => item.visible?.(auth) ?? true)
	const isActive = items.some((item) => isCurrentOrParentUrl(item.href))

	return (
		<SidebarGroup className="px-3 py-0">
			<SidebarMenu>
				<Collapsible
					defaultOpen={isActive}
					className="group/collapsible">
					<SidebarMenuItem>
						<CollapsibleTrigger asChild>
							<SidebarMenuButton className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
								<ShieldCheck className="h-5 w-5" />
								<span>Admin</span>
								<ChevronRight className="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-90" />
							</SidebarMenuButton>
						</CollapsibleTrigger>
						<CollapsibleContent>
							<SidebarMenuSub>
								{items.map((item) => (
									<SidebarMenuSubItem key={item.href}>
										<SidebarMenuSubButton
											asChild
											isActive={isCurrentOrParentUrl(item.href)}>
											<Link
												href={item.href}
												target={item.newTab ? "_blank" : undefined}
												rel={item.newTab ? "noopener" : undefined}>
												<item.icon className="size-4" />
												<span>{item.title}</span>
											</Link>
										</SidebarMenuSubButton>
									</SidebarMenuSubItem>
								))}
							</SidebarMenuSub>
						</CollapsibleContent>
					</SidebarMenuItem>
				</Collapsible>
			</SidebarMenu>
		</SidebarGroup>
	)
}
