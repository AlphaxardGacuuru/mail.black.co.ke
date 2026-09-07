import { Tag } from "lucide-react"
import { Link } from "@/components/ui/link"
import { mainNavItems } from "@/components/app-sidebar"
import {
	SidebarGroup,
	SidebarGroupLabel,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { toUrl } from "@/lib/utils"
import { useLabels } from "@/queries/mail"

export function MailNav() {
	const { isCurrentUrl } = useCurrentUrl()
	const { data: labels } = useLabels()
	const { isMobile, setOpen, setOpenMobile } = useSidebar()

	function closeSidebar(): void {
		if (isMobile) {
			setOpenMobile(false)
			return
		}

		setOpen(false)
	}

	return (
		<SidebarGroup className="px-2 py-0">
			<SidebarGroupLabel>Platform</SidebarGroupLabel>
			<SidebarMenu>
				{mainNavItems.map((item) => {
					const href = toUrl(item.href)
					const Icon = item.icon

					return (
						<SidebarMenuItem key={href}>
							<SidebarMenuButton
								asChild
								isActive={isCurrentUrl(href)}
								tooltip={item.title}>
								<Link
									href={href}
									onClick={closeSidebar}>
									{Icon ? <Icon /> : null}
									<span>{item.title}</span>
								</Link>
							</SidebarMenuButton>
						</SidebarMenuItem>
					)
				})}

				{labels?.map((label) => (
					<SidebarMenuItem key={label.id}>
						<SidebarMenuButton
							asChild
							isActive={isCurrentUrl(`/mail/labels/${label.id}`)}
							tooltip={label.name}>
							<Link
								href={`/mail/labels/${label.id}`}
								onClick={closeSidebar}>
								<Tag style={label.color ? { color: label.color } : undefined} />
								<span>{label.name}</span>
							</Link>
						</SidebarMenuButton>
					</SidebarMenuItem>
				))}
			</SidebarMenu>
		</SidebarGroup>
	)
}
