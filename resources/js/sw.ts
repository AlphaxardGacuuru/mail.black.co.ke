/// <reference lib="webworker" />
import { cleanupOutdatedCaches, precacheAndRoute } from "workbox-precaching"
import { registerRoute } from "workbox-routing"
import { CacheFirst, NetworkFirst, NetworkOnly } from "workbox-strategies"
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
self.addEventListener("activate", (event) => {
	// Mail used to be cached under "api-stale"; it's "api-mail" now. Cached
	// pages are dropped too: they link the previous build's assets, which this
	// version's precache no longer holds.
	event.waitUntil(
		Promise.all([caches.delete("api-stale"), caches.delete("navigation")])
			.then(() => self.clients.claim())
	)
})

// ─── Share target ─────────────────────────────────────────────────────────────
// A job post shared to the installed app (text, a link, images) is kept in
// the "shared-post" cache for the "Add a job" page to pick up, which then
// opens. See share_target in manifest.webmanifest.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin && url.pathname === "/share-target",
	async ({ request }) => {
		const form = await request.formData()
		const cache = await caches.open("shared-post")
		const images = form
			.getAll("images")
			.filter((value): value is File => value instanceof File && value.size > 0)

		await Promise.all(
			(await cache.keys()).map((request) => cache.delete(request))
		)
		await Promise.all(
			images.map((image, index) =>
				cache.put(
					`/shared-post/image-${index}`,
					new Response(image, {
						headers: {
							"Content-Type": image.type,
							"X-File-Name": encodeURIComponent(image.name),
						},
					})
				)
			)
		)
		await cache.put(
			"/shared-post/post",
			new Response(
				JSON.stringify({
					title: String(form.get("title") ?? ""),
					text: String(form.get("text") ?? ""),
					url: String(form.get("url") ?? ""),
					images: images.length,
				}),
				{ headers: { "Content-Type": "application/json" } }
			)
		)

		return Response.redirect("/job-applications/add?shared=1", 303)
	},
	"POST"
)

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

// Notifications and chats change the moment they're read or deleted, so a
// refetch must reach the network (falling back to the cache only when
// offline); stale-while-revalidate would hand back the list from before the
// change.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/notifications"),
	new NetworkFirst({ cacheName: "api-notifications" })
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/chat/") &&
		request.method === "GET",
	new NetworkFirst({ cacheName: "api-chat", networkTimeoutSeconds: 4 })
)

// Mail read routes: network-first, for the same reason. New mail is
// announced over Reverb and the list refetched; stale-while-revalidate
// answered that refetch with the list from before the mail arrived, so it
// only showed up on the next visit while the unread badge already counted
// it. The cache still serves threads, messages and labels offline or on a
// network too slow to answer in time.
// Auth-sensitive routes are intentionally excluded — see NETWORK_ONLY_APIS.
const MAIL_APIS = ["/api/threads", "/api/labels"]

registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		MAIL_APIS.some((prefix) => url.pathname.startsWith(prefix)),
	new NetworkFirst({
		cacheName: "api-mail",
		networkTimeoutSeconds: 4,
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

type PushAction = { action: string; title: string }

// What each button does, keyed by its action name (see
// App\Notifications\Messages\WebPushMessage): open a page, or post to a
// signed link in the background.
type PushActionLink = { url?: string; post?: string }

type PushNotificationData = {
	url?: string
	actions?: Record<string, PushActionLink>
}

type PushPayload = {
	title?: string
	body?: string
	icon?: string
	badge?: string
	tag?: string
	renotify?: boolean
	actions?: PushAction[]
	data?: PushNotificationData
}

self.addEventListener("push", (event) => {
	if (!event.data) {
		return
	}

	const payload: PushPayload = event.data.json()

	// `renotify` and `actions` (the notification's buttons) are real,
	// supported NotificationOptions fields missing from TypeScript's bundled
	// DOM lib.
	const options: NotificationOptions & {
		renotify?: boolean
		actions?: PushAction[]
	} = {
		body: payload.body,
		icon: payload.icon ?? "/notification-icon-192x192.png",
		badge: payload.badge ?? "/notification-badge-192x192.png",
		tag: payload.tag,
		renotify: payload.renotify,
		actions: payload.actions,
		data: payload.data,
	}

	event.waitUntil(
		self.registration.showNotification(
			payload.title ?? "New notification",
			options
		)
	)
})

// Opens the URL (or focuses it if already open in some tab). A button can
// also open a page outside the app, like a job posting.
function openUrl(url: string) {
	return self.clients
		.matchAll({ type: "window", includeUncontrolled: true })
		.then((clients) => {
			const target = new URL(url, self.location.origin).href
			const existing = clients.find((client) => client.url === target)

			if (existing) {
				return existing.focus()
			}

			return self.clients.openWindow(target)
		})
}

// Posts a button's signed link. The signature stands in for the user's
// token, which the service worker can't read from the tab's localStorage.
// If it fails (the link expired or the phone is offline) the
// notification's page opens instead, so the user can do it in the app.
function runSignedAction(post: string, fallbackUrl?: string) {
	return fetch(new URL(post, self.location.origin), {
		method: "POST",
		headers: { Accept: "application/json" },
	})
		.then((response) => {
			if (!response.ok) {
				throw new Error(`Push action failed with ${response.status}`)
			}
		})
		.catch(() => (fallbackUrl ? openUrl(fallbackUrl) : undefined))
}

self.addEventListener("notificationclick", (event) => {
	event.notification.close()

	const data = event.notification.data as PushNotificationData | undefined
	// event.action is empty for a click on the notification itself.
	const link = event.action ? data?.actions?.[event.action] : undefined

	if (link?.post) {
		event.waitUntil(runSignedAction(link.post, data?.url))

		return
	}

	const url = link?.url ?? data?.url

	if (!url) {
		return
	}

	event.waitUntil(openUrl(url))
})

// ─── SPA navigation fallback ──────────────────────────────────────────────────
// All navigate requests that don't match a precached URL fall back to /index.php
// so TanStack Router handles routing on the client side. Kept for a month so
// the app shell still opens while offline, not just within the first minute.
// After the phone has been idle the first request often stalls instead of
// failing, which left a blank screen until a reload, so the cached page is
// used whenever the network hasn't answered within 3 seconds.
registerRoute(
	({ request }) => request.mode === "navigate",
	new NetworkFirst({
		cacheName: "navigation",
		networkTimeoutSeconds: 3,
		plugins: [
			new ExpirationPlugin({ maxEntries: 10, maxAgeSeconds: 30 * 24 * 60 * 60 }),
		],
	})
)
