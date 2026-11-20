import { config } from '../config'
import { basicAuth } from '../api/client'
import { browserSession, clearCredentials, getCredentials } from './session'

/**
 * Logs this device out of Nextcloud:
 * 1. revokes the device's app password on the server (it disappears from "Devices & sessions"),
 * 2. ends the browser session if the page was opened with one — otherwise the app would
 *    immediately turn that session into a new app password again,
 * 3. forgets the local credentials.
 * Cached list data is cleared by the caller (store reset).
 */
export async function logoutDevice(): Promise<void> {
	const creds = await getCredentials()
	if (creds) {
		try {
			await fetch(config.ocsUrl.replace(/\/apps\/groceries\/api\/v1$/, '/core/apppassword'), {
				method: 'DELETE',
				credentials: 'omit',
				headers: { Authorization: basicAuth(creds.loginName, creds.appPassword), 'OCS-APIRequest': 'true' },
			})
		} catch {
			// offline: the password stays valid until revoked in the Nextcloud security settings
		}
	}
	// a browser session of the same Nextcloud (cookie) is ended too
	const session = await browserSession()
	if (session) {
		// Nextcloud's logout clears site data (incl. service workers); a worker killed in the middle
		// of the following reload makes it fail. Unregister it ourselves first (re-registered on start).
		try {
			const regs = await navigator.serviceWorker?.getRegistrations() ?? []
			await Promise.all(regs.map((r) => r.unregister()))
		} catch {
			// no service worker support
		}
		try {
			// follow the redirect: loading /login?clear=1 is what removes the stale session cookies
			await fetch(config.logoutUrl + '?requesttoken=' + encodeURIComponent(session.token), { credentials: 'same-origin', cache: 'no-store' })
		} catch {
			// ignore
		}
	}
	await clearCredentials()
}
