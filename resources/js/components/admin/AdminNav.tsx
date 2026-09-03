import { ChevronRight, LayoutDashboard, ShieldCheck, Webhook } from "lucide-react"
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
import { useCurrentUrl } from "@/hooks/use-current-url"

const ADMIN_ITEMS = [
	{ title: "Dashboard", href: "/admin/dashboard", icon: LayoutDashboard },
	{ title: "Webhooks", href: "/admin/webhooks", icon: Webhook },
]

export function AdminNav() {
	const { isCurrentOrParentUrl } = useCurrentUrl()
	const isActive = ADMIN_ITEMS.some((item) => isCurrentOrParentUrl(item.href))

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
								{ADMIN_ITEMS.map((item) => (
									<SidebarMenuSubItem key={item.href}>
										<SidebarMenuSubButton
											asChild
											isActive={isCurrentOrParentUrl(item.href)}>
											<Link href={item.href}>
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
