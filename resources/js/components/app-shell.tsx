import { useApp } from "@/contexts/AppContext"
import type { ReactNode } from "react"
import { FloatingUserAvatar } from "@/components/floating-user-avatar"
import { SidebarProvider } from "@/components/ui/sidebar"
import type { AppVariant } from "@/types"

type Props = {
	children: ReactNode
	variant?: AppVariant
}

export function AppShell({ children, variant = "sidebar" }: Props) {
	const { auth } = useApp()

	const shouldRenderFloatingAvatar = Boolean(auth)

	const backdropLines = (
		<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
			<div className="absolute inset-0 bg-[repeating-linear-gradient(45deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(45deg,rgba(248,250,252,0.18)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
			<div className="absolute inset-0 bg-[repeating-linear-gradient(135deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(135deg,rgba(248,250,252,0.18)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
		</div>
	)

	if (variant === "header") {
		return (
			<div className="relative flex min-h-screen w-full flex-col">
				{backdropLines}
				{children}
				{shouldRenderFloatingAvatar && <FloatingUserAvatar />}
			</div>
		)
	}

	return (
		<div className="relative">
			{backdropLines}
			<SidebarProvider defaultOpen={true}>
				{children}
				{/* {shouldRenderFloatingAvatar && <FloatingUserAvatar />} */}
			</SidebarProvider>
		</div>
	)
}
