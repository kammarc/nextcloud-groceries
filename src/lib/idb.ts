import { del as idbDel, get as idbGet, set as idbSet } from 'idb-keyval'

/**
 * idb-keyval with two guards against a WebKit bug: on iPhone the first IndexedDB request after
 * the app (or Safari) was relaunched can hang forever, never succeeding nor failing. The app
 * awaits IndexedDB before it mounts, so it stayed on a blank page.
 *
 *  1. wake(): polling indexedDB.databases() gets the stuck IndexedDB going again (the approach of
 *     idb-keyval's safari-14-idb-fix). Done for every browser that has databases(): iOS home-screen
 *     apps have no "Safari" in their user agent, and it is harmless elsewhere.
 *  2. Every operation gets a time limit and then fails like any other IndexedDB error, which the
 *     callers already handle (work online, ask to connect).
 */

const WAKE_LIMIT_MS = 2000
const OP_LIMIT_MS = 4000

export class IdbTimeoutError extends Error {
	constructor() {
		super('IndexedDB did not answer')
		this.name = 'IdbTimeoutError'
	}
}

function withLimit<T>(promise: Promise<T>, ms: number): Promise<T> {
	let timer: ReturnType<typeof setTimeout> | undefined
	const limit = new Promise<never>((_, reject) => {
		timer = setTimeout(() => reject(new IdbTimeoutError()), ms)
	})
	return Promise.race([promise, limit]).finally(() => clearTimeout(timer))
}

let woken: Promise<void> | null = null

function wake(): Promise<void> {
	if (woken) return woken
	const db = globalThis.indexedDB
	if (!db || typeof db.databases !== 'function') {
		woken = Promise.resolve()
		return woken
	}
	let interval: ReturnType<typeof setInterval> | undefined
	const poll = new Promise<void>((resolve) => {
		const ping = () => {
			try {
				db.databases().then(() => resolve(), () => resolve())
			} catch {
				resolve()
			}
		}
		interval = setInterval(ping, 100)
		ping()
	})
	// give up waiting after a while and just try: the operation has its own time limit
	woken = withLimit(poll, WAKE_LIMIT_MS).catch(() => {}).finally(() => clearInterval(interval))
	return woken
}

export async function get<T>(key: string): Promise<T | undefined> {
	await wake()
	return withLimit(idbGet<T>(key), OP_LIMIT_MS)
}

export async function set(key: string, value: unknown): Promise<void> {
	await wake()
	return withLimit(idbSet(key, value), OP_LIMIT_MS)
}

export async function del(key: string): Promise<void> {
	await wake()
	return withLimit(idbDel(key), OP_LIMIT_MS)
}
