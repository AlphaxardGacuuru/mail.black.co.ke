/// <reference lib="webworker" />
import {
	cleanupOutdatedCaches,
	precacheAndRoute,
} from "workbox-precaching"
import { registerRoute } from "workbox-routing"
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from "workbox-strategies"
import { ExpirationPlugin } from "workbox-expiration"
import { BackgroundSyncPlugin } from "workbox-background-sync"

declare const self: ServiceWorkerGlobalScope

// ─── Precache Vite build assets ───────────────────────────────────────────────
// The build manifest is injected here by vite-plugin-pwa at build time.
precacheAndRoute(self.__WB_MANIFEST)
cleanupOutdatedCaches()

self.skipWaiting()
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

// Dashboard & property read API routes: stale-while-revalidate for snappy loads.
// Auth-sensitive routes (payments, billing) are intentionally excluded.
const STALE_WHILE_REVALIDATE_APIS = [
	"/api/dashboard",
	"/api/properties",
	"/api/notifications",
	"/api/staff",
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
				maxEntries: 60,
				maxAgeSeconds: 5 * 60, // 5 minutes max staleness
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
	new NetworkFirst({
		cacheName: "mutations",
		plugins: [bgSyncPlugin],
	}),
	"PUT"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkFirst({
		cacheName: "mutations",
		plugins: [bgSyncPlugin],
	}),
	"DELETE"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkFirst({
		cacheName: "mutations",
		plugins: [bgSyncPlugin],
	}),
	"POST"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkFirst({
		cacheName: "mutations",
		plugins: [bgSyncPlugin],
	}),
	"PATCH"
)

// ─── SPA navigation fallback ──────────────────────────────────────────────────
// All navigate requests that don't match a precached URL fall back to /index.php
// so TanStack Router handles routing on the client side.
registerRoute(
	({ request }) => request.mode === "navigate",
	new NetworkFirst({
		cacheName: "navigation",
		plugins: [
			new ExpirationPlugin({ maxEntries: 1, maxAgeSeconds: 60 }),
		],
	})
)
