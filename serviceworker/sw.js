/* Groceries service worker.
 * Served by PageController::serviceWorker(), which replaces the __PLACEHOLDERS__.
 * Strategy:
 *  - app shell (navigations inside the scope): network first, cached copy when offline
 *  - versioned app assets (js/css/img): cache first
 *  - API calls (/ocs/) and everything else: not intercepted (the app keeps its own IndexedDB cache)
 */
/* eslint-disable no-undef */
const CACHE = 'groceries-' + __CACHE_VERSION__
const SHELL_URL = __SHELL_URL__
const PRECACHE = __PRECACHE__
const ASSET_PREFIX = __APP_ASSET_PREFIX__

self.addEventListener('install', (event) => {
	event.waitUntil((async () => {
		const cache = await caches.open(CACHE)
		await Promise.allSettled([SHELL_URL, ...PRECACHE].map(async (url) => {
			const res = await fetch(url, { credentials: 'same-origin', cache: 'no-cache' })
			if (res.ok) await cache.put(url, res)
		}))
		await self.skipWaiting()
	})())
})

self.addEventListener('activate', (event) => {
	event.waitUntil((async () => {
		for (const key of await caches.keys()) {
			if (key.startsWith('groceries-') && key !== CACHE) await caches.delete(key)
		}
		await self.clients.claim()
	})())
})

self.addEventListener('fetch', (event) => {
	const req = event.request
	if (req.method !== 'GET') return
	const url = new URL(req.url)
	if (url.origin !== self.location.origin) return
	if (url.pathname.includes('/ocs/') || url.pathname.includes('/login/')) return

	if (req.mode === 'navigate') {
		const scopePath = new URL(self.registration.scope).pathname
		if (!url.pathname.startsWith(scopePath)) return
		event.respondWith((async () => {
			const cache = await caches.open(CACHE)
			try {
				const res = await fetch(req)
				if (res.ok && !res.redirected) {
					await cache.put(SHELL_URL, res.clone())
					return res
				}
				// The shell is never redirected legitimately. A redirect or server error (e.g. Nextcloud's
				// SameSite cookie check, maintenance mode) would leave iOS home-screen apps on a blank
				// page, so start from the cached shell instead when there is one.
				if (res.type === 'opaqueredirect' || res.redirected || res.status >= 500) {
					const cached = await cache.match(SHELL_URL)
					if (cached) return cached
				}
				return res
			} catch (e) {
				return (await cache.match(SHELL_URL)) || Response.error()
			}
		})())
		return
	}

	if (url.pathname.startsWith(ASSET_PREFIX)) {
		event.respondWith((async () => {
			const cache = await caches.open(CACHE)
			const hit = await cache.match(req)
			if (hit) return hit
			const res = await fetch(req)
			if (res.ok) await cache.put(req, res.clone())
			return res
		})())
	}
})

self.addEventListener('message', (event) => {
	if (event.data === 'skipWaiting') self.skipWaiting()
})
