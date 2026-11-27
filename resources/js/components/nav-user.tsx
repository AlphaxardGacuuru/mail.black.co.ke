import { Home, LogOut, Settings } from "lucide-react"
import { Link } from "@/components/ui/link"
import {
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	SidebarSeparator,
	useSidebar,
} from "@/components/ui/sidebar"
import { Spinner } from "@/components/ui/spinner"
import { UserInfo } from "@/components/user-info"
import { useApp } from "@/contexts/AppContext"
import { useLogout } from "@/hooks/use-logout"
import { edit } from "@/routes/profile"

const links = [
	{ title: "Welcome Page", href: "/", icon: Home },
	{ title: "Settings", href: edit().url, icon: Settings },
]

/**
 * Lines a 16px icon row up with the 32px avatar in NavUser: the extra
 * left padding centres the icon under the avatar and the wider gap starts
 * the text where the user's name starts. Collapsed, the sidebar's own
 * icon-mode padding takes over.
 */
export const AVATAR_ALIGNED_ITEM = "pl-4 gap-4"

/**
 * The signed-in user and their account links, laid out in the sidebar
 * footer rather than behind a menu.
 */
export function NavUser() {
	const { auth } = useApp()
	const { isMobile, setOpenMobile } = useSidebar()
	const { logout, loggingOut } = useLogout()

	if (!auth) {
		return null
	}

	function closeMobileSidebar(): void {
		if (isMobile) {
			setOpenMobile(false)
		}
	}

	return (
		<SidebarMenu>
			<SidebarSeparator className="mx-0" />
			{links.map((link) => (
				<SidebarMenuItem key={link.title}>
					<SidebarMenuButton
						asChild
						tooltip={link.title}
						className={AVATAR_ALIGNED_ITEM}>
						<Link
							variant="unstyled"
							href={link.href}
							onClick={closeMobileSidebar}>
							<link.icon />
							<span>{link.title}</span>
						</Link>
					</SidebarMenuButton>
				</SidebarMenuItem>
			))}
			<SidebarMenuItem>
				<SidebarMenuButton
					tooltip="Log out"
					className={AVATAR_ALIGNED_ITEM}
					disabled={loggingOut}
					onClick={logout}
					data-test="logout-button">
					{loggingOut ? <Spinner /> : <LogOut />}
					<span>Log out</span>
				</SidebarMenuButton>
			</SidebarMenuItem>
			<SidebarMenuItem>
				<SidebarMenuButton
					asChild
					size="lg"
					tooltip={auth.name}
					className="text-sidebar-accent-foreground hover:bg-transparent active:bg-transparent">
					<div>
						<UserInfo
							user={auth}
							showEmail={true}
						/>
					</div>
				</SidebarMenuButton>
			</SidebarMenuItem>
		</SidebarMenu>
	)
}
