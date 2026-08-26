import { Breadcrumbs } from "@/components/breadcrumbs"
import { MailgunAccountSwitcher } from "@/components/mailgun-account-switcher"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { cn } from "@/lib/utils"
import type { BreadcrumbItem as BreadcrumbItemType } from "@/types"

export function AppSidebarHeader({
	breadcrumbs = [],
	variant = "default",
}: {
	breadcrumbs?: BreadcrumbItemType[]
	variant?: "default" | "floating"
}) {
	return (
		<header
			className={cn(
				"sticky top-0 z-30 flex shrink-0 flex-col gap-2 py-3 text-sidebar-foreground transition-[width,height] ease-linear md:h-16 md:flex-row md:items-center md:py-0 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12",
				variant === "default" &&
					"border-b border-sidebar-border bg-sidebar px-6 md:px-4",
				variant === "floating" &&
					"mx-2 mt-2 rounded-xl border border-white/40 bg-white/34 px-4 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20")}>
			<div className="flex min-w-0 flex-1 items-center gap-2">
				<SidebarTrigger className="-ml-1" />
				<div className="min-w-0 flex-1">
					<Breadcrumbs breadcrumbs={breadcrumbs} />
				</div>
			</div>
			<div className="flex flex-wrap items-center justify-between gap-2">
				<MailgunAccountSwitcher />
			</div>
		</header>
	)
}
