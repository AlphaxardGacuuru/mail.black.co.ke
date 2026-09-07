/// <reference lib="webworker" />
import { cleanupOutdatedCaches, precacheAndRoute } from "workbox-precaching"
import { registerRoute } from "workbox-routing"
import {
	CacheFirst,
	NetworkFirst,
	NetworkOnly,
	StaleWhileRevalidate,
} from "workbox-strategies"
import { ExpirationPlugin } from "workbox-expiration"
import { BackgroundSyncPlugin } from "workbox-background-sync"

declare const self: ServiceWorkerGlobalScope

// ─── Precache Vite build assets ───────────────────────────────────────────────
// The build manifest is injected here by vite-plugin-pwa at build time.
precacheAndRoute(self.__WB_MANIFEST)
cleanupOutdatedCaches()

// Wait for the window to explicitly ask us to take over (see the "Update"
// toast in app.tsx) instead of activating immediately, so an in-progress
// session isn't disrupted by a deploy.
self.addEventListener("message", (event) => {
	if (event.data?.type === "SKIP_WAITING") {
		self.skipWaiting()
	}
})
self.addEventListener("activate", () => self.clients.claim())

// ─── Caching strategies ───────────────────────────────────────────────────────

// Static assets from the Vite build: cache-first, valid for 30 days.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin && url.pathname.startsWith("/build/"),
	new CacheFirst({
		cacheName: "build-assets",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 120,
				maxAgeSeconds: 30 * 24 * 60 * 60,
			}),
		],
	})
)

// Favicons and PWA icons: cache-first, valid for 30 days.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		/\.(png|ico|svg)$/.test(url.pathname),
	new CacheFirst({
		cacheName: "static-icons",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 20,
				maxAgeSeconds: 30 * 24 * 60 * 60,
			}),
		],
	})
)

// Web app manifest: network-first. A long-lived cache here would pin the
// app name/icons shown in the install prompt to whatever they were on the
// first visit, surviving rebrands for up to the cache's expiry.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		url.pathname.endsWith(".webmanifest"),
	new NetworkFirst({
		cacheName: "webmanifest",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 1,
				maxAgeSeconds: 24 * 60 * 60,
			}),
		],
	})
)

// Mail read routes: stale-while-revalidate, so the last-fetched threads,
// messages, and labels are what's shown immediately (including while
// offline), with a background refresh whenever the network is up.
// Auth-sensitive routes are intentionally excluded — see NETWORK_ONLY_APIS.
const STALE_WHILE_REVALIDATE_APIS = [
	"/api/threads",
	"/api/labels",
	"/api/notifications",
]

registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		STALE_WHILE_REVALIDATE_APIS.some((prefix) =>
			url.pathname.startsWith(prefix)
		),
	new StaleWhileRevalidate({
		cacheName: "api-stale",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 200,
				maxAgeSeconds: 7 * 24 * 60 * 60, // keep for offline use across a week of inactivity
			}),
		],
	})
)

// Auth and payment API routes: always network-first, never serve stale data.
const NETWORK_ONLY_APIS = [
	"/api/auth",
	"/api/payments",
	"/api/billing",
	"/api/broadcasting",
]

registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		NETWORK_ONLY_APIS.some((prefix) => url.pathname.startsWith(prefix)),
	new NetworkFirst({ cacheName: "api-auth" })
)

// ─── Background sync for mutation requests ────────────────────────────────────
// Queues PUT/DELETE/POST requests that fail due to lost connectivity and
// replays them automatically once the connection is restored.
const bgSyncPlugin = new BackgroundSyncPlugin("mutation-queue", {
	maxRetentionTime: 24 * 60, // Retry for up to 24 hours
})

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"PUT"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"DELETE"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"POST"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"PATCH"
)

// ─── Web push notifications ───────────────────────────────────────────────────

type PushPayload = {
	title?: string
	body?: string
	icon?: string
	data?: { url?: string }
}

self.addEventListener("push", (event) => {
	if (!event.data) {
		return
	}

	const payload: PushPayload = event.data.json()

	event.waitUntil(
		self.registration.showNotification(payload.title ?? "New notification", {
			body: payload.body,
			icon: payload.icon ?? "/android-chrome-192x192.png",
			data: payload.data,
		})
	)
})

self.addEventListener("notificationclick", (event) => {
	event.notification.close()

	const url = (event.notification.data as { url?: string } | undefined)?.url

	if (!url) {
		return
	}

	event.waitUntil(
		self.clients
			.matchAll({ type: "window", includeUncontrolled: true })
			.then((clients) => {
				const target = new URL(url, self.location.origin).href
				const existing = clients.find((client) => client.url === target)

				if (existing) {
					return existing.focus()
				}

				return self.clients.openWindow(target)
			})
	)
})

// ─── SPA navigation fallback ──────────────────────────────────────────────────
// All navigate requests that don't match a precached URL fall back to /index.php
// so TanStack Router handles routing on the client side. Kept for a month so
// the app shell still opens while offline, not just within the first minute.
registerRoute(
	({ request }) => request.mode === "navigate",
	new NetworkFirst({
		cacheName: "navigation",
		plugins: [
			new ExpirationPlugin({ maxEntries: 1, maxAgeSeconds: 30 * 24 * 60 * 60 }),
		],
	})
)
