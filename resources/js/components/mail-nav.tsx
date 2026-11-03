import { Briefcase, Mail, Tag } from "lucide-react"
import type { LucideIcon } from "lucide-react"
import type { ReactNode } from "react"
import { Link } from "@/components/ui/link"
import { mailNavItems } from "@/components/app-sidebar"
import {
	SidebarGroup,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	SidebarMenuSub,
	SidebarMenuSubButton,
	SidebarMenuSubItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { useCurrentUrl } from "@/hooks/use-current-url"
import {
	JOB_APPLICATIONS_URL,
	jobApplicationNavItems,
} from "@/lib/job-application-tabs"
import { toUrl } from "@/lib/utils"
import { useLabels } from "@/queries/mail"
import type { NavItem } from "@/types"

const MAIL_URL = "/mail"

function useCloseSidebar(): () => void {
	const { isMobile, setOpen, setOpenMobile } = useSidebar()

	return () => {
		if (isMobile) {
			setOpenMobile(false)
			return
		}

		setOpen(false)
	}
}

function NavSubLink({
	href,
	title,
	icon,
}: {
	href: string
	title: string
	icon: ReactNode
}) {
	const { isCurrentUrl } = useCurrentUrl()
	const closeSidebar = useCloseSidebar()

	return (
		<SidebarMenuSubItem>
			<SidebarMenuSubButton
				asChild
				isActive={isCurrentUrl(href)}>
				<Link
					href={href}
					onClick={closeSidebar}>
					{icon}
					<span>{title}</span>
				</Link>
			</SidebarMenuSubButton>
		</SidebarMenuSubItem>
	)
}

/**
 * A section link (Mail, Job applications) with its pages under it. On small
 * screens the bottom nav lists those pages, so only `everyScreen` links stay.
 */
function NavSection({
	title,
	href,
	icon: Icon,
	items,
	everyScreen,
}: {
	title: string
	href: string
	icon: LucideIcon
	items: NavItem[]
	everyScreen?: ReactNode
}) {
	const { currentUrl } = useCurrentUrl()
	const { isMobile, state } = useSidebar()
	const closeSidebar = useCloseSidebar()
	const inSection = currentUrl === href || currentUrl.startsWith(`${href}/`)
	// While its pages are listed, the current page's link is highlighted
	// instead of the section.
	const showsItems = !isMobile && state === "expanded"

	return (
		<SidebarMenuItem>
			<SidebarMenuButton
				asChild
				isActive={inSection && !showsItems}
				tooltip={title}>
				<Link
					href={href}
					onClick={closeSidebar}>
					<Icon />
					<span>{title}</span>
				</Link>
			</SidebarMenuButton>

			{(!isMobile || everyScreen) && (
				<SidebarMenuSub>
					{!isMobile &&
						items.map((item) => {
							const ItemIcon = item.icon

							return (
								<NavSubLink
									key={toUrl(item.href)}
									href={toUrl(item.href)}
									title={item.title}
									icon={ItemIcon ? <ItemIcon /> : null}
								/>
							)
						})}
					{everyScreen}
				</SidebarMenuSub>
			)}
		</SidebarMenuItem>
	)
}

export function MailNav() {
	const { data: labels } = useLabels()

	return (
		<SidebarGroup className="px-2 py-0">
			<SidebarMenu>
				<NavSection
					title="Mail"
					href={MAIL_URL}
					icon={Mail}
					items={mailNavItems}
					everyScreen={
						// Labels aren't in the bottom nav, so they stay on every screen.
						labels && labels.length > 0
							? labels.map((label) => (
									<NavSubLink
										key={label.id}
										href={`/mail/labels/${label.id}`}
										title={label.name}
										icon={
											<Tag
												style={label.color ? { color: label.color } : undefined}
											/>
										}
									/>
								))
							: undefined
					}
				/>
			</SidebarMenu>
		</SidebarGroup>
	)
}

export function JobApplicationsNav() {
	return (
		<SidebarGroup className="px-2 py-0">
			<SidebarMenu>
				<NavSection
					title="Job applications"
					href={JOB_APPLICATIONS_URL}
					icon={Briefcase}
					items={jobApplicationNavItems}
				/>
			</SidebarMenu>
		</SidebarGroup>
	)
}
