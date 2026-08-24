import { Archive, ChevronRight, Inbox, Mail, Send, Star, Tag, Trash2 } from "lucide-react"
import { Link } from "@/components/ui/link"
import {
	Collapsible,
	CollapsibleContent,
	CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
	SidebarGroup,
	SidebarGroupLabel,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	SidebarMenuSub,
	SidebarMenuSubButton,
	SidebarMenuSubItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { useLabels } from "@/queries/mail"

const FOLDERS = [
	{ href: "/mail", label: "Inbox", icon: Inbox },
	{ href: "/mail/starred", label: "Starred", icon: Star },
	{ href: "/mail/sent", label: "Sent", icon: Send },
	{ href: "/mail/archive", label: "Archive", icon: Archive },
	{ href: "/mail/trash", label: "Trash", icon: Trash2 },
]

export function MailNav() {
	const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl()
	const { data: labels } = useLabels()
	const { isMobile, setOpen, setOpenMobile } = useSidebar()
	const isOnMailSection = isCurrentOrParentUrl("/mail")

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
				<Collapsible
					defaultOpen={isOnMailSection}
					className="group/collapsible">
					<SidebarMenuItem>
						<CollapsibleTrigger asChild>
							<SidebarMenuButton
								isActive={isOnMailSection}
								tooltip={{ children: "Mail" }}>
								<Mail />
								<span>Mail</span>
								<ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
							</SidebarMenuButton>
						</CollapsibleTrigger>

						<CollapsibleContent>
							<SidebarMenuSub>
								{FOLDERS.map((folder) => (
									<SidebarMenuSubItem key={folder.href}>
										<SidebarMenuSubButton
											asChild
											isActive={isCurrentUrl(folder.href)}>
											<Link
												href={folder.href}
												onClick={closeSidebar}>
												<folder.icon />
												<span>{folder.label}</span>
											</Link>
										</SidebarMenuSubButton>
									</SidebarMenuSubItem>
								))}

								{labels?.map((label) => (
									<SidebarMenuSubItem key={label.id}>
										<SidebarMenuSubButton
											asChild
											isActive={isCurrentUrl(`/mail/labels/${label.id}`)}>
											<Link
												href={`/mail/labels/${label.id}`}
												onClick={closeSidebar}>
												<Tag style={label.color ? { color: label.color } : undefined} />
												<span>{label.name}</span>
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
