import { Link } from "@/components/ui/link"
import { Download, Mail } from "lucide-react"
import { AdminNav } from "@/components/admin/AdminNav"
import AppLogo from "@/components/app-logo"
import AutoPushPrompt from "@/components/auto-push-prompt"
import { MailNav } from "@/components/mail-nav"
import MailRealtimeNotifier from "@/components/mail/MailRealtimeNotifier"
import { NavFooter } from "@/components/nav-footer"
import { NavNotifications } from "@/components/nav-notifications"
import { NavUser } from "@/components/nav-user"
import { useApp } from "@/contexts/AppContext"
import { usePwaInstall } from "@/hooks/use-pwa-install"
import { ADMIN_EMAIL } from "@/middleware/auth"
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
const HOME_URL = "/mail"
import type { NavItem } from "@/types"

export const mainNavItems: NavItem[] = [
	{
		title: "Mail",
		href: "/mail",
		icon: Mail,
	},
]

const footerNavItems: NavItem[] = [
	{
		title: "Get App",
		href: "/get-app",
		icon: Download,
	},
]

export function AppSidebar() {
	const { state } = useSidebar()
	const { isInstalled } = usePwaInstall()
	const { auth } = useApp()
	const isAdmin = auth?.email === ADMIN_EMAIL

	return (
		<Sidebar
			side="left"
			collapsible="icon"
			variant="floating">
			<MailRealtimeNotifier />
			<AutoPushPrompt />

			<SidebarHeader>
				<div className="flex items-center">
					<SidebarMenu className="min-w-0 flex-1">
						<SidebarMenuItem>
							<SidebarMenuButton
								size="xl"
								asChild>
								<Link href={HOME_URL}>
									{state === "collapsed" ? (
										<AppLogo variant="icon" className="h-8" />
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
				<MailNav />
			</SidebarContent>

			<SidebarFooter>
				{isAdmin && <AdminNav />}
				{!isInstalled && (
					<NavFooter
						items={footerNavItems}
						className="mt-auto"
					/>
				)}
				<NavNotifications />
				<NavUser />
			</SidebarFooter>
		</Sidebar>
	)
}
