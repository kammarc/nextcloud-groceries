import { config } from '../config'
import { getCredentials } from '../auth/session'

export class ApiError extends Error {
	constructor(public status: number, message: string) {
		super(message)
	}
}

/** Thrown when the device is offline or the server cannot be reached. */
export class NetworkError extends Error {}

let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(fn: () => void) {
	onUnauthorized = fn
}

export function basicAuth(loginName: string, password: string): string {
	const bytes = new TextEncoder().encode(`${loginName}:${password}`)
	let bin = ''
	bytes.forEach((b) => { bin += String.fromCharCode(b) })
	return 'Basic ' + btoa(bin)
}

/**
 * Calls the app's OCS API with the device's app password (no cookies, no CSRF token).
 */
export async function api<T>(method: string, path: string, body?: unknown, timeoutMs = 15000): Promise<T> {
	const creds = await getCredentials()
	if (!creds) {
		throw new ApiError(401, 'Not connected')
	}
	const url = config.ocsUrl + path + (path.includes('?') ? '&' : '?') + 'format=json'
	const controller = new AbortController()
	const timer = setTimeout(() => controller.abort(), timeoutMs)
	let res: Response
	try {
		res = await fetch(url, {
			method,
			credentials: 'omit',
			cache: 'no-store',
			signal: controller.signal,
			headers: {
				Authorization: basicAuth(creds.loginName, creds.appPassword),
				'OCS-APIRequest': 'true',
				Accept: 'application/json',
				...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
			},
			body: body !== undefined ? JSON.stringify(body) : undefined,
		})
	} catch (e) {
		throw new NetworkError((e as Error).message)
	} finally {
		clearTimeout(timer)
	}
	let json: any = null
	try {
		json = await res.json()
	} catch {
		// non-JSON error page
	}
	if (res.status === 401) {
		onUnauthorized?.()
		throw new ApiError(401, 'Unauthorized')
	}
	if (!res.ok) {
		const message = json?.ocs?.data?.message ?? json?.ocs?.meta?.message ?? res.statusText
		throw new ApiError(res.status, message || `HTTP ${res.status}`)
	}
	return json?.ocs?.data as T
}

/** Retry-able failures: offline, timeouts, server errors, rate limits. */
export function isTransient(e: unknown): boolean {
	return e instanceof NetworkError || (e instanceof ApiError && (e.status >= 500 || e.status === 408 || e.status === 429))
}
