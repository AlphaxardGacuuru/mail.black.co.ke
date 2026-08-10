import { Link } from "@/components/ui/link"
import { Home, Building, Download } from "lucide-react"
import AppLogo from "@/components/app-logo"
import AppLogoMark from "@/components/app-logo-mark"
import { NavFooter } from "@/components/nav-footer"
import { NavMain } from "@/components/nav-main"
import { NavNotifications } from "@/components/nav-notifications"
import { NavUser } from "@/components/nav-user"
import {
	Sidebar,
	SidebarContent,
	SidebarFooter,
	SidebarHeader,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
const DASHBOARD_URL = "/admin/dashboard"
import type { NavItem } from "@/types"

export const mainNavItems: NavItem[] = [
	{
		title: "Dashboard",
		href: DASHBOARD_URL,
		icon: Home,
	},
	{
		title: "Properties",
		href: "/admin/properties",
		icon: Building,
	},
]

const footerNavItems: NavItem[] = [
	{
		title: "Get App",
		href: "/admin/properties",
		icon: Download,
	},
]

export function AppSidebar() {
	const { state } = useSidebar()

	return (
		<Sidebar
			side="left"
			collapsible="icon"
			variant="floating">
			<SidebarHeader>
				<div className="flex items-center">
					<SidebarMenu className="min-w-0 flex-1">
						<SidebarMenuItem>
							<SidebarMenuButton
								size="lg"
								asChild>
								<Link href={DASHBOARD_URL}>
									{state === "collapsed" ? (
										<div className="flex justify-center w-full text-sidebar-primary-foreground">
											<AppLogoMark className="fill-current text-primary dark:text-white" />
										</div>
									) : (
										<AppLogo />
									)}
								</Link>
							</SidebarMenuButton>
						</SidebarMenuItem>
					</SidebarMenu>
				</div>
			</SidebarHeader>

			<SidebarContent>
				<NavMain items={mainNavItems} />
			</SidebarContent>

			<SidebarFooter>
				<NavFooter
					items={footerNavItems}
					className="mt-auto"
				/>
				<NavNotifications />
				<NavUser />
			</SidebarFooter>
		</Sidebar>
	)
}
