import { configureEcho } from "@laravel/echo-react"
import { Workbox } from "workbox-window"
import Axios from "@/lib/axios"
import { StrictMode, createElement, useMemo } from "react"
import type { ComponentType, ReactNode } from "react"
import { createRoot } from "react-dom/client"
import {
	Outlet,
	RouterProvider,
	createRootRoute,
	createRoute,
	createRouter,
} from "@tanstack/react-router"
import { QueryClientProvider } from "@tanstack/react-query"
import { queryClient } from "@/lib/query-client"
import { requireAuth, requireGuest, requireSuperAdmin } from "@/middleware/auth"
// import { requireSubscribed } from "@/middleware/subscribed"
import { AppPageProvider, useLayoutProps, usePage } from "@/lib/spa"
import { Toaster, FlashToastHandler } from "@/components/ui/sonner"
import { TooltipProvider } from "@/components/ui/tooltip"
import { initializeTheme } from "@/hooks/use-appearance"
import AppLayout from "@/layouts/app-layout"
import AuthLayout from "@/layouts/auth-layout"
import SettingsLayout from "@/layouts/settings/layout"
import { AppProvider } from "@/contexts/AppContext"
import { discoverPageRoutes } from "@/router/page-routes"
import NotFound from "@/components/not-found"

// ─── Types ───────────────────────────────────────────────────────────────────

type RegisteredPageRoute = {
	componentName: string
	path: string
}

type SpaPage = {
	component: string
	props: Record<string, unknown>
	url: string
}

// ─── Router ──────────────────────────────────────────────────────────────────

const GUEST_ONLY = new Set([
	"auth/login",
	"auth/register",
	"auth/forgot-password",
	"auth/reset-password",
	"auth/two-factor-challenge",
])

const PUBLIC = new Set(["welcome", "auth/socialite-callback"])

const pages = import.meta.glob<{
	default: ComponentType<Record<string, unknown>>
}>("./pages/**/*.tsx", { eager: true })

const discoveredRoutes: RegisteredPageRoute[] = discoverPageRoutes(
	Object.keys(pages),
	"./pages/"
)

// Mounts once for the entire app lifetime — survives navigation
function RootLayout() {
	return (
		<QueryClientProvider client={queryClient}>
			<AppProvider>
				<TooltipProvider delayDuration={0}>
					<Outlet />
				</TooltipProvider>
				<Toaster />
			</AppProvider>
		</QueryClientProvider>
	)
}

const rootRoute = createRootRoute({
	component: RootLayout,
	notFoundComponent: NotFound,
})

const tanstackRoutes = discoveredRoutes.map((discoveredRoute) => {
	let route: any = null

	const component = () => {
		if (!route) {
			throw new Error("Route instance was not initialized.")
		}

		const params = route.useParams() as Record<string, string>
		const email = new URLSearchParams(window.location.search).get("email")

		const sharedProps: Record<string, unknown> = {
			auth: { user: null },
			sidebarOpen: true,
			name: "Property Black",
		}

		const pageSpecificProps: Record<string, unknown> = {
			...params,
			...(discoveredRoute.componentName === "auth/reset-password"
				? { email: email ?? "" }
				: {}),
		}

		const page: SpaPage = {
			component: discoveredRoute.componentName,
			props: { ...sharedProps, ...pageSpecificProps },
			url: `${window.location.pathname}${window.location.search}`,
		}

		return (
			<AppPageProvider page={page}>
				<PageRenderer />
				<FlashToastHandler />
			</AppPageProvider>
		)
	}

	const routePath =
		discoveredRoute.componentName === "welcome"
			? "/"
			: discoveredRoute.path.replace(/^\//, "")

	route = createRoute({
		getParentRoute: () => rootRoute,
		path: routePath,
		component,
		beforeLoad: GUEST_ONLY.has(discoveredRoute.componentName)
			? requireGuest
			: PUBLIC.has(discoveredRoute.componentName)
				? undefined
				: async (ctx) => {
						await requireAuth()
						await requireSuperAdmin(ctx)
						// await requireSubscribed(ctx)
					},
	})

	return route
})

const routeTree = rootRoute.addChildren(tanstackRoutes)

const router = createRouter({ routeTree, defaultNotFoundComponent: NotFound })

declare module "@tanstack/react-router" {
	interface Register {
		router: typeof router
	}
}

// ─── Components ──────────────────────────────────────────────────────────────

function resolvePage(componentName: string) {
	const modulePath = `./pages/${componentName}.tsx`
	const resolved = pages[modulePath]

	if (!resolved) {
		throw new Error(`Unable to resolve page component: ${componentName}`)
	}

	return resolved.default
}

function applyLayout(
	componentName: string,
	element: ReactNode,
	layoutProps: Record<string, unknown>
) {
	switch (true) {
		case componentName === "welcome":
			return element
		case componentName.startsWith("auth/"):
			return <AuthLayout {...layoutProps}>{element}</AuthLayout>
		case componentName.startsWith("settings/"):
			return (
				<AppLayout {...layoutProps}>
					<SettingsLayout>{element}</SettingsLayout>
				</AppLayout>
			)
		default:
			return <AppLayout {...layoutProps}>{element}</AppLayout>
	}
}

function PageRenderer() {
	const page = usePage<Record<string, unknown>>()
	const dynamicLayoutProps = useLayoutProps()

	const PageComponent = useMemo(
		() => resolvePage(page.component),
		[page.component]
	)

	const staticLayoutProps =
		typeof (PageComponent as { layout?: unknown }).layout === "object" &&
		(PageComponent as { layout?: unknown }).layout !== null
			? ((PageComponent as { layout?: Record<string, unknown> }).layout ?? {})
			: {}

	const mergedLayoutProps = { ...staticLayoutProps, ...dynamicLayoutProps }

	return applyLayout(
		page.component,
		createElement(PageComponent, page.props),
		mergedLayoutProps
	)
}

// ─── Mount ───────────────────────────────────────────────────────────────────

const container = document.getElementById("app")

if (!container) {
	throw new Error("Unable to mount application: #app not found.")
}

type RootedContainer = HTMLElement & {
	_reactRoot?: ReturnType<typeof createRoot>
}

const rootedContainer = container as RootedContainer

if (!rootedContainer._reactRoot) {
	rootedContainer._reactRoot = createRoot(container)
}

rootedContainer._reactRoot.render(
	<StrictMode>
		<RouterProvider
			router={router}
			defaultPendingMs={0}
		/>
	</StrictMode>
)

if ("serviceWorker" in navigator) {
	const wb = new Workbox("/sw.js")

	// When a new service worker is waiting, reload once it activates so
	// users always get the latest build assets without a manual refresh.
	wb.addEventListener("waiting", () => {
		wb.messageSkipWaiting()
	})

	wb.register().catch(() => {
		// Ignore registration failures and keep the web app functional.
	})
}

initializeTheme()

configureEcho({
	broadcaster: "reverb",
	key: import.meta.env.VITE_REVERB_APP_KEY,
	wsHost: import.meta.env.VITE_REVERB_HOST,
	wsPort: import.meta.env.VITE_REVERB_PORT,
	wssPort: import.meta.env.VITE_REVERB_PORT,
	forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
	enabledTransports: ["ws", "wss"],
	authorizer: (channel) => {
		return {
			authorize: (socketId, callback) => {
				Axios
					.post("/api/broadcasting/auth", {
						socket_id: socketId,
						channel_name: channel.name,
					})
					.then((response) => callback(null, response.data))
					.catch((error: unknown) => {
						const callbackError =
							error instanceof Error ? error : new Error("Broadcast auth failed")

						callback(callbackError, null)
					})
			},
		}
	},
})
