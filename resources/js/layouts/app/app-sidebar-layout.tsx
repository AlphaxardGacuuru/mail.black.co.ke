import { AppBottomNav } from "@/components/app-bottom-nav"
import { AppContent } from "@/components/app-content"
import { AppShell } from "@/components/app-shell"
import { AppSidebar } from "@/components/app-sidebar"
import { AppSidebarHeader } from "@/components/app-sidebar-header"
import NoInternet from "@/components/no-internet"
import { useOnlineStatus } from "@/hooks/use-online-status"
import type { AppLayoutProps } from "@/types"

export default function AppSidebarLayout({
	children,
	breadcrumbs = [],
}: AppLayoutProps) {
	const isOnline = useOnlineStatus()

	return (
		<AppShell variant="sidebar">
			<AppSidebar />
			<AppContent
				variant="sidebar"
				className="bg-transparent pb-24 md:pb-0">
				<AppSidebarHeader breadcrumbs={breadcrumbs} variant="floating" />
				<div className="flex flex-1 flex-col gap-4 overflow-x-hidden p-4">
					{isOnline ? children : <NoInternet />}
				</div>
			</AppContent>
			<AppBottomNav />
		</AppShell>
	)
}
