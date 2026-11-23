/**
 * Languages of the app (UI texts in src/locales/<code>.json, catalog names in catalog/i18n/<code>.json).
 * Keep in sync with PageController::LANGUAGES.
 */
export const LANGUAGES = [
	{ code: 'en', name: 'English' },
	{ code: 'de', name: 'Deutsch' },
	{ code: 'fr', name: 'Français' },
	{ code: 'es', name: 'Español' },
	{ code: 'it', name: 'Italiano' },
	{ code: 'pt', name: 'Português' },
	{ code: 'nl', name: 'Nederlands' },
	{ code: 'pl', name: 'Polski' },
	{ code: 'cs', name: 'Čeština' },
	{ code: 'sv', name: 'Svenska' },
	{ code: 'da', name: 'Dansk' },
	{ code: 'nb', name: 'Norsk bokmål' },
	{ code: 'fi', name: 'Suomi' },
	{ code: 'hu', name: 'Magyar' },
	{ code: 'ro', name: 'Română' },
	{ code: 'tr', name: 'Türkçe' },
	{ code: 'ru', name: 'Русский' },
	{ code: 'uk', name: 'Українська' },
	{ code: 'el', name: 'Ελληνικά' },
	{ code: 'ja', name: '日本語' },
] as const

export type Lang = typeof LANGUAGES[number]['code']

const CODES = new Set<string>(LANGUAGES.map((l) => l.code))

export const isLang = (v: unknown): v is Lang => typeof v === 'string' && CODES.has(v)

/**
 * Best supported language for a locale tag such as "pt_BR", "de-CH" or "no" (null if none).
 * Norwegian ("no", "nn") falls back to bokmål.
 */
export function matchLang(tag: string | null | undefined): Lang | null {
	if (!tag) return null
	const base = tag.toLowerCase().split(/[-_]/)[0]
	if (base === 'no' || base === 'nn') return 'nb'
	return isLang(base) ? base : null
}
