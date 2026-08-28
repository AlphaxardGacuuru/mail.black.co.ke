import { Breadcrumbs } from "@/components/breadcrumbs"
import { MailgunAccountSwitcher } from "@/components/mailgun-account-switcher"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { useRouterState } from "@tanstack/react-router"
import { useLabels, useMailThread } from "@/queries/mail"
import { cn } from "@/lib/utils"
import type { BreadcrumbItem as BreadcrumbItemType } from "@/types"

export function AppSidebarHeader({
	breadcrumbs = [],
	variant = "default",
}: {
	breadcrumbs?: BreadcrumbItemType[]
	variant?: "default" | "floating"
}) {
	const pathname = useRouterState({
		select: (state) => state.location.pathname,
	})
	const { data: labels } = useLabels()
	const labelId = pathname.match(/^\/mail\/labels\/([^/]+)/)?.[1]
	const threadId = pathname.match(/^\/mail\/([^/]+)\/show/)?.[1]
	const { data: thread } = useMailThread(threadId ?? null)
	const folder = pathname.match(/^\/mail\/([^/]+)/)?.[1]
	const folderTitles: Record<string, string> = {
		archive: "Archive",
		sent: "Sent",
		starred: "Starred",
		trash: "Trash",
		compose: "New message",
	}
	const mailTitle = threadId
		? (thread?.subject || "(no subject)")
		: labelId
			? (labels?.find((label) => label.id === labelId)?.name ?? "Label")
			: pathname === "/mail" || pathname === "/mail/"
				? "Inbox"
				: folder === "labels"
					? "Mail"
					: folder
						? (folderTitles[folder] ?? folder.replace(/-/g, " "))
						: null
	const displayedBreadcrumbs = mailTitle
		? [{ title: mailTitle, href: pathname }]
		: breadcrumbs


	return (
		<header
			className={cn(
				"sticky top-0 z-30 flex shrink-0 flex-col gap-2 py-3 text-sidebar-foreground transition-[width,height] ease-linear md:h-16 md:flex-row md:items-center md:py-0 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12",
				variant === "default" &&
					"border-b border-sidebar-border bg-sidebar px-6 md:px-4",
				variant === "floating" &&
					"mx-2 mt-2 rounded-xl border border-white/40 bg-white/34 px-4 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20"
			)}>
			<div className="flex min-w-0 flex-1 items-center gap-2">
				<SidebarTrigger className="-ml-1" />
				<div className="min-w-0 flex-1">
					<Breadcrumbs breadcrumbs={displayedBreadcrumbs} />
				</div>
				<MailgunAccountSwitcher />
			</div>
		</header>
	)
}
