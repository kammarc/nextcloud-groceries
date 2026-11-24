import { get, set, del, IdbTimeoutError } from '../lib/idb'
import { config } from '../config'

/**
 * Device credentials: an app password obtained once via Login Flow v2 (or from an existing
 * browser session). App passwords do not expire, so the installed app stays logged in until the
 * device is revoked in Nextcloud's Security settings or the user logs out in the app.
 *
 * At rest the credentials are encrypted (AES-GCM, 256 bit) with a key that is generated on the
 * device as non-extractable: the browser can use it, but nobody can read it out, so a copy of
 * the browser profile or of IndexedDB does not contain a usable password.
 */
export interface Credentials {
	loginName: string
	appPassword: string
	server: string
}

interface Sealed {
	v: 1
	iv: Uint8Array
	data: ArrayBuffer
}

const KEY = 'groceries:credentials'
const KEY_DEVICE = 'groceries:device-key'
let cached: Credentials | null | undefined

const subtle = (): SubtleCrypto | null => (globalThis.crypto?.subtle && globalThis.isSecureContext !== false ? globalThis.crypto.subtle : null)

async function deviceKey(): Promise<CryptoKey | null> {
	const s = subtle()
	if (!s) return null
	const existing = await get<CryptoKey>(KEY_DEVICE)
	if (existing) return existing
	const key = await s.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'])
	await set(KEY_DEVICE, key)
	return key
}

async function seal(c: Credentials): Promise<Sealed | Credentials> {
	const key = await deviceKey()
	if (!key) return c // no WebCrypto (plain-HTTP test setups): stored as is
	const iv = crypto.getRandomValues(new Uint8Array(12))
	const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(JSON.stringify(c)))
	return { v: 1, iv, data }
}

async function unseal(stored: unknown): Promise<{ creds: Credentials | null, legacy: boolean }> {
	if (!stored || typeof stored !== 'object') return { creds: null, legacy: false }
	if ('appPassword' in stored) return { creds: stored as Credentials, legacy: true }
	const sealed = stored as Sealed
	const key = await deviceKey()
	if (!key || sealed.v !== 1) return { creds: null, legacy: false }
	const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: sealed.iv }, key, sealed.data)
	return { creds: JSON.parse(new TextDecoder().decode(plain)) as Credentials, legacy: false }
}

export async function getCredentials(): Promise<Credentials | null> {
	if (cached !== undefined) return cached
	try {
		const { creds, legacy } = await unseal(await get(KEY))
		cached = creds
		// stored by an older version in plain text: encrypt it now
		if (creds && legacy) await set(KEY, await seal(creds))
	} catch (e) {
		// IndexedDB hangs (iOS, see lib/idb.ts): not "logged out", so ask again next time instead
		// of remembering null
		if (e instanceof IdbTimeoutError) return null
		cached = null
	}
	return cached
}

export async function saveCredentials(c: Credentials): Promise<void> {
	cached = c
	try {
		await set(KEY, await seal(c))
	} catch {
		// private mode: keep in memory for this session
	}
}

export async function clearCredentials(): Promise<void> {
	cached = null
	try {
		await del(KEY)
		await del(KEY_DEVICE)
	} catch {
		// ignore
	}
}

/**
 * The Nextcloud browser session of this origin, if there is one: its user and a fresh CSRF
 * token. Asked for only when needed, so the cached app page never contains either.
 */
export async function browserSession(): Promise<{ user: string, token: string } | null> {
	try {
		const who = await fetch(config.sessionUserUrl + '?format=json', {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
		})
		if (!who.ok) return null
		const user = (await who.json())?.ocs?.data?.id
		if (typeof user !== 'string' || user === '') return null
		const res = await fetch(config.csrfTokenUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
		if (!res.ok) return null
		const token = (await res.json())?.token
		return typeof token === 'string' && token !== '' ? { user, token } : null
	} catch {
		return null
	}
}

/**
 * If the page was opened with a live Nextcloud browser session (common on Android, where the
 * installed app shares Chrome's cookies), turn it into an app password without any login screen.
 */
export async function tryConvertSession(): Promise<boolean> {
	const session = await browserSession()
	if (!session) return false
	try {
		const res = await fetch(config.appPasswordUrl + '?format=json', {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'OCS-APIRequest': 'true', requesttoken: session.token, Accept: 'application/json' },
		})
		if (!res.ok) return false
		const json = await res.json()
		const appPassword = json?.ocs?.data?.apppassword
		if (!appPassword) return false
		await saveCredentials({ loginName: session.user, appPassword, server: config.serverUrl })
		return true
	} catch {
		return false
	}
}

export interface LoginFlow {
	loginUrl: string
	pollUrl: string
	token: string
}

/** Same-origin version of a URL the server built (it may use a different scheme/host behind proxies). */
function sameOrigin(url: string): string {
	const u = new URL(url, window.location.href)
	return window.location.origin + u.pathname + u.search
}

/** The login page must be a web page of this Nextcloud (never e.g. a javascript: URL). */
function safeLoginUrl(url: unknown): string {
	if (typeof url !== 'string') throw new Error('Login flow: no login URL')
	const u = new URL(url, window.location.href)
	if (u.protocol !== 'https:' && u.protocol !== 'http:') throw new Error('Login flow: invalid login URL')
	if (u.protocol === 'http:' && window.location.protocol === 'https:') u.protocol = 'https:'
	return u.href
}

/** HTTPS, or a local test server. Over plain HTTP the app password would travel unencrypted. */
export function isSecureConnection(): boolean {
	const host = window.location.hostname
	return window.location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1' || host === '[::1]'
}

export async function startLoginFlow(): Promise<LoginFlow> {
	const res = await fetch(config.loginFlowUrl, { method: 'POST', credentials: 'omit', cache: 'no-store', headers: { Accept: 'application/json' } })
	if (!res.ok) throw new Error(`Login flow failed (${res.status})`)
	const json = await res.json()
	return { loginUrl: safeLoginUrl(json.login), pollUrl: sameOrigin(json.poll.endpoint), token: json.poll.token }
}

/** One poll; resolves true when the user has granted access. */
export async function pollLoginFlow(flow: LoginFlow): Promise<boolean> {
	const res = await fetch(flow.pollUrl, {
		method: 'POST',
		credentials: 'omit',
		cache: 'no-store',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
		body: 'token=' + encodeURIComponent(flow.token),
	})
	if (res.status === 404) return false
	if (!res.ok) throw new Error(`Login poll failed (${res.status})`)
	const json = await res.json()
	if (typeof json?.loginName !== 'string' || typeof json?.appPassword !== 'string') throw new Error('Login poll: invalid answer')
	await saveCredentials({ loginName: json.loginName, appPassword: json.appPassword, server: String(json.server ?? '') })
	return true
}
