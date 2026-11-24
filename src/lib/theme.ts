import { ref } from 'vue'

/**
 * Background scheme, colour of the "to buy" entries and light/dark mode (see theme.css), chosen
 * independently per device in the app settings.
 */
export const THEMES = ['blue', 'red', 'green', 'purple'] as const
export type ThemeName = typeof THEMES[number]
export type ThemeMode = 'dark' | 'light'
export const ENTRY_COLORS = ['coral', 'amber', 'orange', 'pink'] as const
export type EntryColor = typeof ENTRY_COLORS[number]
/** Font of the item names (tiles, rows, add sheet); see --app-item-font in theme.css. */
export const ITEM_FONTS = ['standard', 'patrick', 'quicksand'] as const
export type ItemFont = typeof ITEM_FONTS[number]
/** Until one is chosen on the device ('standard' = the system font). */
const DEFAULT_ITEM_FONT: ItemFont = 'quicksand'
/** CSS font family of the bundled fonts (@font-face in theme.css); also shown as their name. */
export const ITEM_FONT_FAMILY: Record<Exclude<ItemFont, 'standard'>, string> = {
	patrick: 'Patrick Hand',
	quicksand: 'Quicksand',
}

/** The entry colour each scheme came with; the default until one is chosen. */
const SCHEME_ENTRIES: Record<ThemeName, EntryColor> = { blue: 'coral', red: 'amber', green: 'orange', purple: 'pink' }

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

function read(): { theme: ThemeName, mode: ThemeMode, entries: EntryColor, itemFont: ItemFont, sketchIcons: boolean } {
	try {
		const v = JSON.parse(localStorage.getItem(KEY) ?? '{}')
		const scheme: ThemeName = THEMES.includes(v.theme) ? v.theme : 'blue'
		return {
			theme: scheme,
			mode: v.mode === 'light' ? 'light' : 'dark',
			entries: ENTRY_COLORS.includes(v.entries) ? v.entries : SCHEME_ENTRIES[scheme],
			itemFont: ITEM_FONTS.includes(v.itemFont) ? v.itemFont : DEFAULT_ITEM_FONT,
			sketchIcons: v.sketchIcons === true,
		}
	} catch {
		return { theme: 'blue', mode: 'dark', entries: 'coral', itemFont: DEFAULT_ITEM_FONT, sketchIcons: false }
	}
}

const initial = read()
export const theme = ref<ThemeName>(initial.theme)
export const mode = ref<ThemeMode>(initial.mode)
export const entries = ref<EntryColor>(initial.entries)
export const itemFont = ref<ItemFont>(initial.itemFont)
/** "Hand-drawn icons": item icons as one-colour pen sketches instead of colour emoji (lib/sketch.ts). */
export const sketchIcons = ref<boolean>(initial.sketchIcons)

/** Applies the current scheme to <html> (CSS variables in theme.css) and the system bars. */
export function applyTheme() {
	const html = document.documentElement
	html.dataset.theme = theme.value
	html.dataset.mode = mode.value
	html.dataset.entries = entries.value
	html.dataset.itemFont = itemFont.value
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

export function setTheme(next: { theme?: ThemeName, mode?: ThemeMode, entries?: EntryColor, itemFont?: ItemFont, sketchIcons?: boolean }) {
	if (next.theme) theme.value = next.theme
	if (next.mode) mode.value = next.mode
	if (next.entries) entries.value = next.entries
	if (next.itemFont) itemFont.value = next.itemFont
	if (next.sketchIcons !== undefined) sketchIcons.value = next.sketchIcons
	try {
		localStorage.setItem(KEY, JSON.stringify({ theme: theme.value, mode: mode.value, entries: entries.value, itemFont: itemFont.value, sketchIcons: sketchIcons.value }))
	} catch {
		// private mode: applies for this session only
	}
	applyTheme()
}
