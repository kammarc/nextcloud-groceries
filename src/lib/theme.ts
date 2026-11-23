import { ref } from 'vue'

/** Colour schemes (see theme.css) and light/dark mode, chosen per device in the app settings. */
export const THEMES = ['blue', 'red', 'green', 'purple'] as const
export type ThemeName = typeof THEMES[number]
export type ThemeMode = 'dark' | 'light'

/**
 * Page colour per scheme/mode, for the browser/status bar (meta theme-color).
 * Keep in sync with PageController::BAR.
 */
const BAR: Record<ThemeName, Record<ThemeMode, string>> = {
	green: { dark: '#153b33', light: '#eef5f1' },
	blue: { dark: '#13283f', light: '#eef3f9' },
	red: { dark: '#3a1520', light: '#f8eff1' },
	purple: { dark: '#271a3a', light: '#f3f0f8' },
}

const KEY = 'groceries:theme'
/** Read by PageController (index, manifest). */
const COOKIE = 'groceries_theme'

function read(): { theme: ThemeName, mode: ThemeMode } {
	try {
		const v = JSON.parse(localStorage.getItem(KEY) ?? '{}')
		return {
			theme: THEMES.includes(v.theme) ? v.theme : 'blue',
			mode: v.mode === 'light' ? 'light' : 'dark',
		}
	} catch {
		return { theme: 'blue', mode: 'dark' }
	}
}

const initial = read()
export const theme = ref<ThemeName>(initial.theme)
export const mode = ref<ThemeMode>(initial.mode)

/** Applies the current scheme to <html> (CSS variables in theme.css) and the system bars. */
export function applyTheme() {
	const html = document.documentElement
	html.dataset.theme = theme.value
	html.dataset.mode = mode.value
	let meta = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
	if (!meta) {
		meta = document.createElement('meta')
		meta.name = 'theme-color'
		document.head.appendChild(meta)
	}
	meta.content = BAR[theme.value][mode.value]
	document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]')
		?.setAttribute('content', mode.value === 'light' ? 'default' : 'black-translucent')
	document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', mode.value)
	writeCookie()
}

/**
 * Mirrors the choice into a cookie, so the server renders the page (meta theme-color, iOS
 * status bar style) and the manifest in the chosen colours. Home-screen apps take the system
 * bar colours from those at launch and ignore later changes made by script.
 */
function writeCookie() {
	try {
		document.cookie = `${COOKIE}=${theme.value}-${mode.value}; path=/; max-age=315360000; SameSite=Lax`
			+ (location.protocol === 'https:' ? '; Secure' : '')
	} catch {
		// cookies blocked: the page still switches, only the system bars keep the default colour
	}
}

export function setTheme(next: { theme?: ThemeName, mode?: ThemeMode }) {
	if (next.theme) theme.value = next.theme
	if (next.mode) mode.value = next.mode
	try {
		localStorage.setItem(KEY, JSON.stringify({ theme: theme.value, mode: mode.value }))
	} catch {
		// private mode: applies for this session only
	}
	applyTheme()
}
