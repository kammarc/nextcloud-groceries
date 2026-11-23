/**
 * Backup of the whole library: the server exports every list with items, stores, categories,
 * history, statistics and per-list preferences; the device adds its own app settings.
 */
import { api } from '../api/client'
import { getLanguagePreference, setLanguagePreference, type LanguagePreference } from '../i18n'
import { isLang } from './languages'
import { setWakeLockEnabled, wakeLockEnabled } from './platform'
import { mode, setTheme, theme, THEMES, type ThemeMode, type ThemeName } from './theme'

export const LIBRARY_FORMAT = 'groceries-library'

export interface AppSettings {
	theme: ThemeName
	mode: ThemeMode
	language: LanguagePreference
	wakeLock: boolean
}

export interface Library {
	format: typeof LIBRARY_FORMAT
	version: number
	exportedAt: string
	lists: Array<{ name: string, items?: unknown[], [k: string]: unknown }>
	settings?: Partial<AppSettings>
	[k: string]: unknown
}

export interface ImportResult {
	lists: number
	items: number
	history: number
	events: number
	skipped: number
	deleted: number
}

export type ImportMode = 'add' | 'replace'

/** Large libraries take a while (pictures, years of statistics). */
const TIMEOUT = 180_000

export function currentSettings(): AppSettings {
	return { theme: theme.value, mode: mode.value, language: getLanguagePreference(), wakeLock: wakeLockEnabled.value }
}

export async function fetchLibrary(): Promise<Library> {
	const lib = await api<Library>('GET', '/library', undefined, TIMEOUT)
	return { ...lib, settings: currentSettings() }
}

/** Throws on anything that is not a library export. */
export function parseLibrary(text: string): Library {
	let data: any
	try {
		data = JSON.parse(text)
	} catch {
		throw new Error('not a Groceries library export')
	}
	if (data?.format !== LIBRARY_FORMAT || !Array.isArray(data.lists)) throw new Error('not a Groceries library export')
	return data as Library
}

export async function importLibrary(library: Library, importMode: ImportMode): Promise<ImportResult> {
	const { settings: _settings, ...serverPart } = library
	return api<ImportResult>('POST', '/library', { library: serverPart, mode: importMode }, TIMEOUT)
}

/** Applies the app settings of an export on this device. Returns true when the language changed (needs a reload). */
export function applySettings(s: Partial<AppSettings> | undefined): boolean {
	if (!s || typeof s !== 'object') return false
	const next: { theme?: ThemeName, mode?: ThemeMode } = {}
	if (THEMES.includes(s.theme as ThemeName)) next.theme = s.theme
	if (s.mode === 'light' || s.mode === 'dark') next.mode = s.mode
	if (next.theme || next.mode) setTheme(next)
	if (typeof s.wakeLock === 'boolean') setWakeLockEnabled(s.wakeLock)
	if ((s.language === 'auto' || isLang(s.language)) && s.language !== getLanguagePreference()) {
		setLanguagePreference(s.language)
		return true
	}
	return false
}
