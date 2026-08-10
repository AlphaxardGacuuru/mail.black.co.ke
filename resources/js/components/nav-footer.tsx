import type { ComponentPropsWithoutRef } from "react"
import {
	SidebarGroup,
	SidebarGroupContent,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
import { toUrl } from "@/lib/utils"
import type { NavItem } from "@/types"
import { Link } from "@/components/ui/link"

export function NavFooter({
	items,
	className,
	...props
}: ComponentPropsWithoutRef<typeof SidebarGroup> & {
	items: NavItem[]
}) {
	const { isMobile, setOpen, setOpenMobile } = useSidebar()

	function closeSidebar(): void {
		if (isMobile) {
			setOpenMobile(false)

			return
		}

		setOpen(false)
	}

	return (
		<SidebarGroup
			{...props}
			className={`group-data-[collapsible=icon]:p-0 ${className || ""}`}>
			<SidebarGroupContent>
				<SidebarMenu>
					{items.map((item) => (
						<SidebarMenuItem key={item.title}>
							<SidebarMenuButton
								asChild
								className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
								<Link
									href={toUrl(item.href)}
									rel="noopener noreferrer"
									onClick={closeSidebar}>
									{item.icon && <item.icon className="h-5 w-5" />}
									<span>{item.title}</span>
								</Link>
							</SidebarMenuButton>
						</SidebarMenuItem>
					))}
				</SidebarMenu>
			</SidebarGroupContent>
		</SidebarGroup>
	)
}
