import { Link } from "@/components/ui/link"
import { findActiveNavHref, mainNavItems } from "@/components/app-sidebar"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { shouldHideBottomNav } from "@/lib/bottom-nav"
import { cn } from "@/lib/utils"

export function AppBottomNav() {
	const { currentUrl } = useCurrentUrl()

	if (shouldHideBottomNav(currentUrl)) {
		return null
	}

	const activeHref = findActiveNavHref(currentUrl, mainNavItems)

	return (
		<div className="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-3 pb-3 md:hidden">
			<nav className="bg-sidebar text-sidebar-foreground border-sidebar-border pointer-events-auto mx-auto flex max-w-md items-center justify-around rounded-2xl border shadow-sm backdrop-blur supports-backdrop-filter:bg-sidebar/95">
				{mainNavItems.map((item) => {
					const href = typeof item.href === "string" ? item.href : item.href.url
					const isActive = href === activeHref
					const Icon = item.icon

					return (
						<Link
							key={item.title}
							href={href}
							variant="unstyled"
							size="none"
							aria-current={isActive ? "page" : undefined}
							className="flex min-w-0 flex-1 justify-center">
							<span
								className={cn(
									"flex w-full flex-col items-center gap-1 rounded-2xl px-3 py-2 text-[11px] font-medium transition-colors",
									isActive
										? "bg-primary text-secondary"
										: "text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
								)}>
								<span
									className={cn(
										"flex size-11 items-center justify-center rounded-2xl border border-transparent transition-all",
										isActive ? "text-secondary" : "text-sidebar-foreground"
									)}>
									{Icon ? <Icon className="size-10" /> : null}
								</span>
								<span className="truncate">{item.title}</span>
							</span>
						</Link>
					)
				})}
			</nav>
		</div>
	)
}
