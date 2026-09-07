import { Link } from "@/components/ui/link"
import { Archive, Download, Inbox, Send, Star, Trash2 } from "lucide-react"
import { AdminNav } from "@/components/admin/AdminNav"
import AppLogo from "@/components/app-logo"
import { MailNav } from "@/components/mail-nav"
import MailRealtimeNotifier from "@/components/mail/MailRealtimeNotifier"
import { NavFooter } from "@/components/nav-footer"
import { NavNotifications } from "@/components/nav-notifications"
import { NavUser } from "@/components/nav-user"
import PermissionsOnboarding from "@/components/permissions-onboarding"
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
import { toUrl } from "@/lib/utils"

// Sibling routes like /chats and /chats/archived both start with "/chats",
// so a plain prefix match would leave both nav items active on the archived
// page. Picking the longest matching href resolves the ambiguity in favor
// of the more specific route, and generalizes to any future nav items with
// overlapping prefixes.
export function findActiveNavHref(
	pathname: string,
	items: NavItem[]
): string | null {
	let best: string | null = null

	for (const item of items) {
		const href = toUrl(item.href)
		const matches = pathname === href || pathname.startsWith(`${href}/`)

		if (matches && (best === null || href.length > best.length)) {
			best = href
		}
	}

	return best
}

export const mainNavItems: NavItem[] = [
	{ title: "Inbox", href: "/mail", icon: Inbox },
	{ title: "Starred", href: "/mail/starred", icon: Star },
	{ title: "Sent", href: "/mail/sent", icon: Send },
	{ title: "Archive", href: "/mail/archive", icon: Archive },
	{ title: "Trash", href: "/mail/trash", icon: Trash2 },
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
			<PermissionsOnboarding />

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
				{isAdmin && <AdminNav />}
			</SidebarContent>

			<SidebarFooter>
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
