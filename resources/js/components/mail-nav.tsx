import { Tag } from "lucide-react"
import type { ReactNode } from "react"
import { Link } from "@/components/ui/link"
import { mailNavItems } from "@/components/app-sidebar"
import {
	SidebarGroup,
	SidebarGroupLabel,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { jobApplicationNavItems } from "@/lib/job-application-tabs"
import { toUrl } from "@/lib/utils"
import { useLabels } from "@/queries/mail"

function NavLinkItem({
	href,
	title,
	icon,
}: {
	href: string
	title: string
	icon: ReactNode
}) {
	const { isCurrentUrl } = useCurrentUrl()
	const { isMobile, setOpen, setOpenMobile } = useSidebar()

	function closeSidebar(): void {
		if (isMobile) {
			setOpenMobile(false)
			return
		}

		setOpen(false)
	}

	return (
		<SidebarMenuItem>
			<SidebarMenuButton
				asChild
				isActive={isCurrentUrl(href)}
				tooltip={title}>
				<Link
					href={href}
					onClick={closeSidebar}>
					{icon}
					<span>{title}</span>
				</Link>
			</SidebarMenuButton>
		</SidebarMenuItem>
	)
}

export function MailNav() {
	const { data: labels } = useLabels()

	return (
		<SidebarGroup className="px-2 py-0">
			<SidebarGroupLabel>Mail</SidebarGroupLabel>
			<SidebarMenu>
				{mailNavItems.map((item) => {
					const Icon = item.icon

					return (
						<NavLinkItem
							key={toUrl(item.href)}
							href={toUrl(item.href)}
							title={item.title}
							icon={Icon ? <Icon /> : null}
						/>
					)
				})}

				{labels?.map((label) => (
					<NavLinkItem
						key={label.id}
						href={`/mail/labels/${label.id}`}
						title={label.name}
						icon={<Tag style={label.color ? { color: label.color } : undefined} />}
					/>
				))}
			</SidebarMenu>
		</SidebarGroup>
	)
}

export function JobApplicationsNav() {
	return (
		<SidebarGroup className="px-2 py-0">
			<SidebarGroupLabel>Job applications</SidebarGroupLabel>
			<SidebarMenu>
				{jobApplicationNavItems.map((item) => {
					const Icon = item.icon

					return (
						<NavLinkItem
							key={toUrl(item.href)}
							href={toUrl(item.href)}
							title={item.title}
							icon={Icon ? <Icon /> : null}
						/>
					)
				})}
			</SidebarMenu>
		</SidebarGroup>
	)
}
