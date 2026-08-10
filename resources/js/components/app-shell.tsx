import { useApp } from "@/contexts/AppContext"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { detectPortal, getPortalVisual } from "@/lib/portal-context"
import type { ReactNode } from "react"
import { FloatingUserAvatar } from "@/components/floating-user-avatar"
import { SidebarProvider } from "@/components/ui/sidebar"
import { cn } from "@/lib/utils"
import type { AppVariant } from "@/types"

type Props = {
	children: ReactNode
	variant?: AppVariant
}

export function AppShell({ children, variant = "sidebar" }: Props) {
	const { auth } = useApp()
	const { currentUrl } = useCurrentUrl()
	const portalVisual = getPortalVisual(detectPortal(currentUrl))

	const shouldRenderFloatingAvatar = Boolean(auth)

	const backdropLines = (
		<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
			<div className="absolute inset-0 bg-[repeating-linear-gradient(45deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
			<div className="absolute inset-0 bg-[repeating-linear-gradient(135deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
		</div>
	)

	if (variant === "header") {
		return (
			<div
				className={cn(
					"relative flex min-h-screen w-full flex-col",
					portalVisual.shellClass
				)}>
				{backdropLines}
				{children}
				{shouldRenderFloatingAvatar && <FloatingUserAvatar />}
			</div>
		)
	}

	return (
		<div className={cn("relative", portalVisual.shellClass)}>
			{backdropLines}
			<SidebarProvider defaultOpen={true}>
				{children}
				{/* {shouldRenderFloatingAvatar && <FloatingUserAvatar />} */}
			</SidebarProvider>
		</div>
	)
}
