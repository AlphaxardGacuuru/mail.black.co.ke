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
	// Mail used to be cached under "api-stale"; it's "api-mail" now.
	event.waitUntil(
		caches
			.delete("api-stale")
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

type PushNotificationData = { url?: string; conversationId?: string }

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

	// `renotify` and `actions` (chat's reply/mark read/delete) are real,
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

// Opens the conversation (or focuses it if already open in some tab), the
// fallback for a reply that couldn't be sent from the service worker.
// `draft` prefills the message composer so a typed reply is never lost.
function openConversation(url: string, draft?: string) {
	const targetUrl = draft ? `${url}?draft=${encodeURIComponent(draft)}` : url

	return self.clients
		.matchAll({ type: "window", includeUncontrolled: true })
		.then((clients) => {
			const targetPath = new URL(url, self.location.origin).pathname
			const existing = clients.find(
				(client) => new URL(client.url).pathname === targetPath
			)

			if (existing) {
				return existing.focus()
			}

			return self.clients.openWindow(
				new URL(targetUrl, self.location.origin).href
			)
		})
}

// A same-origin request carrying whatever session cookie the browser has.
// A service worker can't reach the bearer token an open tab keeps in
// localStorage, so this only authenticates for session-cookie logins and
// fails silently for everyone else.
function apiFetch(path: string, method: string, body?: unknown) {
	return fetch(path, {
		method,
		credentials: "include",
		headers: {
			Accept: "application/json",
			"X-Requested-With": "XMLHttpRequest",
			...(body ? { "Content-Type": "application/json" } : {}),
		},
		body: body ? JSON.stringify(body) : undefined,
	}).then((response) => {
		if (!response.ok) {
			throw new Error(`Request to ${path} failed with ${response.status}`)
		}
	})
}

self.addEventListener("notificationclick", (event) => {
	event.notification.close()

	const data = event.notification.data as PushNotificationData | undefined

	if (!data?.url) {
		return
	}

	const conversationId = data.conversationId

	if (!conversationId) {
		event.waitUntil(openUrl(data.url))

		return
	}

	// Inline reply text, typed straight into the notification; only some
	// browsers support it and TypeScript's NotificationEvent lacks it.
	const reply = (event as unknown as { reply?: string }).reply

	if (event.action === "mark-read") {
		event.waitUntil(
			apiFetch(`/api/chat/conversations/${conversationId}/read`, "POST").catch(
				() => {}
			)
		)

		return
	}

	if (event.action === "delete") {
		event.waitUntil(
			apiFetch(`/api/chat/conversations/${conversationId}`, "DELETE").catch(
				() => {}
			)
		)

		return
	}

	if (event.action === "reply" && reply) {
		event.waitUntil(
			apiFetch(`/api/chat/conversations/${conversationId}/messages`, "POST", {
				body: reply,
			}).catch(() => openConversation(data.url!, reply))
		)

		return
	}

	event.waitUntil(openConversation(data.url))
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
