/** Server-provided boot configuration (PageController::index → data-config). */
export interface AppConfig {
	version: string
	catalogVersion: string
	/** the user's Nextcloud language (locale code such as "pt_BR"); the app picks the closest it has */
	language: string
	serverName: string
	serverUrl: string
	appUrl: string
	ocsUrl: string
	loginFlowUrl: string
	appPasswordUrl: string
	serviceWorkerUrl: string
	imgUrl: string
	/** OCS endpoint that names the user of the browser session (cookie), if there is one */
	sessionUserUrl: string
	/** fresh CSRF token of the browser session; never part of the (cached) page itself */
	csrfTokenUrl: string
	/** Nextcloud browser-session logout (needs a CSRF token) */
	logoutUrl: string
	themeColor: string
}

function read(): AppConfig {
	const el = typeof document !== 'undefined' ? document.getElementById('groceries-app') : null
	const raw = el?.dataset.config
	const fallback: AppConfig = {
		version: 'dev',
		catalogVersion: '',
		language: 'en',
		serverName: 'Nextcloud',
		serverUrl: '/',
		appUrl: '/apps/groceries/',
		ocsUrl: '/ocs/v2.php/apps/groceries/api/v1',
		loginFlowUrl: '/index.php/login/v2',
		appPasswordUrl: '/ocs/v2.php/core/getapppassword',
		serviceWorkerUrl: '/apps/groceries/sw.js',
		imgUrl: '/apps/groceries/img/',
		sessionUserUrl: '/ocs/v2.php/cloud/user',
		csrfTokenUrl: '/index.php/csrftoken',
		logoutUrl: '/index.php/logout',
		themeColor: '#2e9d5b',
	}
	try {
		return raw ? { ...fallback, ...JSON.parse(raw) } : fallback
	} catch {
		return fallback
	}
}

export const config: AppConfig = read()
