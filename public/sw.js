const CACHE_NAME = "money-black-v2"
const ASSETS_TO_CACHE = [
	"/",
	"/manifest.webmanifest",
	"/favicon.ico",
	"/favicon.svg",
	"/favicon-16x16.png",
	"/favicon-32x32.png",
	"/apple-touch-icon.png",
	"/android-chrome-192x192.png",
	"/android-chrome-512x512.png",
]

self.addEventListener("install", (event) => {
	event.waitUntil(
		caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS_TO_CACHE))
	)

	self.skipWaiting()
})

self.addEventListener("activate", (event) => {
	event.waitUntil(
		caches
			.keys()
			.then((keys) =>
				Promise.all(
					keys
						.filter((key) => key !== CACHE_NAME)
						.map((key) => caches.delete(key))
				)
			)
	)

	self.clients.claim()
})

self.addEventListener("fetch", (event) => {
	if (event.request.method !== "GET") {
		return
	}

	const url = new URL(event.request.url)
	const isApiRequest =
		url.origin === self.location.origin && url.pathname.startsWith("/api/")
	const isXhrRequest =
		event.request.headers.get("X-Requested-With") === "XMLHttpRequest"
	const isStaticAssetRequest =
		url.origin === self.location.origin &&
		(ASSETS_TO_CACHE.includes(url.pathname) ||
			url.pathname.startsWith("/build/"))

	// Dynamic API requests and XHR requests should always hit the network.
	if (isApiRequest || isXhrRequest) {
		event.respondWith(fetch(event.request))

		return
	}

	if (event.request.mode === "navigate") {
		event.respondWith(fetch(event.request).catch(() => caches.match("/")))

		return
	}

	// Non-static assets use network-first to avoid stale UI/data.
	if (!isStaticAssetRequest) {
		event.respondWith(
			fetch(event.request).catch(() => caches.match(event.request))
		)

		return
	}

	event.respondWith(
		caches.match(event.request).then((cachedResponse) => {
			if (cachedResponse) {
				return cachedResponse
			}

			return fetch(event.request)
				.then((networkResponse) => {
					if (
						!networkResponse ||
						networkResponse.status !== 200 ||
						networkResponse.type !== "basic"
					) {
						return networkResponse
					}

					const responseToCache = networkResponse.clone()

					caches.open(CACHE_NAME).then((cache) => {
						cache.put(event.request, responseToCache)
					})

					return networkResponse
				})
				.catch(() => cachedResponse)
		})
	)
})
