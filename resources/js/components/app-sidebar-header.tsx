import { useQuery } from "@tanstack/react-query"
import { Breadcrumbs } from "@/components/breadcrumbs"
import { Link } from "@/components/ui/link"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from "@/components/ui/select"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { useApp } from "@/contexts/AppContext"
import { useCurrentUrl } from "@/hooks/use-current-url"
import Axios from "@/lib/axios"
import {
	detectPortal,
	getAvailablePortals,
	getPortalHref,
	getPortalVisual,
} from "@/lib/portal-context"
import { cn } from "@/lib/utils"
import { Building2, Check, ChevronDown, House, Shield } from "lucide-react"
import type { LucideIcon } from "lucide-react"
import type { BreadcrumbItem as BreadcrumbItemType } from "@/types"

type PropertyOption = {
	id: number | string
	name: string
}

const portalIcons: Record<"super" | "admin" | "tenant", LucideIcon> = {
	super: Shield,
	admin: Building2,
	tenant: House,
}

export function AppSidebarHeader({
	breadcrumbs = [],
	variant = "default",
}: {
	breadcrumbs?: BreadcrumbItemType[]
	variant?: "default" | "floating"
}) {
	const props = useApp()
	const { currentUrl } = useCurrentUrl()
	const currentPortal = detectPortal(currentUrl)
	const portalVisual = getPortalVisual(currentPortal)
	const isAdminRoute = currentUrl.startsWith("/admin/")
	const CurrentPortalIcon = portalIcons[currentPortal]
	const portals = props.auth ? getAvailablePortals(props.auth, currentUrl) : []

	const propertyIds = [
		...((props.auth?.propertyIds as string[]) ?? []),
		...((props.auth?.assignedPropertyIds as string[]) ?? []),
	]
	const idsParam = propertyIds.length > 0 ? propertyIds.join(",") : "0"

	const { data: properties = [] } = useQuery({
		queryKey: ["properties-select", idsParam],
		queryFn: () =>
			Axios.get("/api/properties", {
				params: { idAndName: true, propertyId: idsParam },
			}).then((res) => res.data.data as PropertyOption[]),
		enabled: !!props.auth && isAdminRoute,
	})

	return (
		<header
			className={cn(
				"sticky top-0 z-30 flex shrink-0 flex-col gap-2 py-3 text-sidebar-foreground transition-[width,height] ease-linear md:h-16 md:flex-row md:items-center md:py-0 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12",
				variant === "default" &&
					"border-b border-sidebar-border bg-sidebar px-6 md:px-4",
				variant === "floating" &&
					"mx-2 mt-2 rounded-xl border border-white/40 bg-white/34 px-4 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20",
				portalVisual.headerClass
			)}>
			<div className="flex min-w-0 flex-1 items-center gap-2">
				<SidebarTrigger className="-ml-1" />
				<div className="min-w-0 flex-1">
					<Breadcrumbs breadcrumbs={breadcrumbs} />
				</div>
			</div>
			<div className="flex flex-wrap justify-between items-center gap-2">
				{/* Properties Dropdown Start */}
				{isAdminRoute && (
					<Select
						value={props.selectedPropertyId}
						onValueChange={props.setSelectedPropertyId}>
						<SelectTrigger
							className="w-40"
							size="sm">
							<SelectValue placeholder="All" />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="All">All</SelectItem>
							{properties.map((property) => (
								<SelectItem
									key={property.id}
									value={String(property.id)}
									className="cursor-pointer">
									{property.name}
								</SelectItem>
							))}
						</SelectContent>
					</Select>
				)}
				{/* Properties Dropdown End */}
				{/* Portal Dropdown Start */}
				{props.auth && (
					<DropdownMenu>
						<DropdownMenuTrigger asChild>
							<button
								type="button"
								className={cn(
									"inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-semibold tracking-wide transition-colors",
									portalVisual.badgeClass
								)}>
								<CurrentPortalIcon className="size-3.5" />
								<span>{portalVisual.label} Portal</span>
								<ChevronDown className="size-3.5 opacity-80" />
							</button>
						</DropdownMenuTrigger>
						<DropdownMenuContent
							align="end"
							className="w-56 rounded-lg">
							{portals.map((portal) => {
								const menuPortalVisual = getPortalVisual(portal)
								const MenuPortalIcon = portalIcons[portal]
								const isCurrentPortal = portal === currentPortal

								return (
									<DropdownMenuItem
										key={portal}
										asChild>
										<Link
											variant="unstyled"
											className={cn(
												"flex w-full items-center justify-between rounded-sm px-2 py-1.5",
												isCurrentPortal && "bg-accent"
											)}
											href={getPortalHref(portal, currentUrl)}>
											<span className="flex items-center gap-2">
												<MenuPortalIcon className="size-4" />
												<span>{menuPortalVisual.label} Portal</span>
											</span>
											{isCurrentPortal && <Check className="size-4" />}
										</Link>
									</DropdownMenuItem>
								)
							})}
						</DropdownMenuContent>
					</DropdownMenu>
				)}
				{/* Portal Dropdown End */}
			</div>
		</header>
	)
}
