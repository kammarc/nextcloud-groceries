import { config } from './config'
import { isLang, matchLang, type Lang } from './lib/languages'

/**
 * UI translations: src/locales/<code>.json, English text as the key (English needs no file).
 * Plural forms beyond singular/plural (Polish, Czech, Russian, …) are extra keys "<plural>|<category>",
 * e.g. "items|few", with the categories of Intl.PluralRules.
 */
const files = import.meta.glob<Record<string, string>>('./locales/*.json', { eager: true, import: 'default' })
const dictionaries: Partial<Record<Lang, Record<string, string>>> = {}
for (const [path, dict] of Object.entries(files)) {
	const code = path.match(/([a-z]+)\.json$/)?.[1]
	if (isLang(code)) dictionaries[code] = dict
}

/** For the translation completeness test. */
export const dictionary = (code: Lang): Record<string, string> => dictionaries[code] ?? {}

export type LanguagePreference = 'auto' | Lang
const LANG_KEY = 'groceries:language'

/** Language chosen in the app's settings on this device ('auto' = follow Nextcloud/browser). */
export function getLanguagePreference(): LanguagePreference {
	try {
		const v = localStorage.getItem(LANG_KEY)
		return isLang(v) ? v : 'auto'
	} catch {
		return 'auto'
	}
}

/** Stores the preference; callers reload the page to apply it everywhere. */
export function setLanguagePreference(pref: LanguagePreference) {
	try {
		if (pref === 'auto') localStorage.removeItem(LANG_KEY)
		else localStorage.setItem(LANG_KEY, pref)
	} catch {
		// private mode: not persisted
	}
}

function detect(): Lang {
	const pref = typeof localStorage !== 'undefined' ? getLanguagePreference() : 'auto'
	if (pref !== 'auto') return pref
	// Nextcloud's language of the user, else the browser's languages in order of preference
	const browser = typeof navigator !== 'undefined' ? (navigator.languages?.length ? navigator.languages : [navigator.language]) : []
	for (const tag of [config.language, ...browser]) {
		const match = matchLang(tag)
		if (match) return match
	}
	return 'en'
}

export const lang: Lang = detect()

const dict: Record<string, string> = dictionaries[lang] ?? {}

export function t(key: string, vars: Record<string, string | number> = {}): string {
	const s = dict[key] ?? key
	return s.replace(/\{(\w+)\}/g, (_, k) => String(vars[k] ?? `{${k}}`))
}

const pluralRules = new Intl.PluralRules(lang)

/** "1 item" / "3 items", with the extra plural forms of languages that have more than two. */
export function n(count: number, singular: string, plural: string): string {
	const category = pluralRules.select(count)
	const special = category !== 'one' && category !== 'other' ? dict[`${plural}|${category}`] : undefined
	return `${count} ${special ?? t(category === 'one' ? singular : plural)}`
}
