/**
 * Built-in catalog (bundled for offline use). Matching mirrors lib/Service/CatalogService.php.
 */
import data from '../../lib/Catalog/catalog.json'
import type { Lang } from './languages'
import { normalize, variants } from './normalize'

export type { Lang }

/** English and German are complete (with synonyms and brands); the other languages have names only. */
type Names = { en: string, de: string } & Partial<Record<Lang, string>>

export interface CatalogItem {
	key: string
	category: string
	icon: string
	names: Names
	synonyms?: Partial<Record<Lang, string[]>>
	staple?: boolean
	/** category on pharmacy/hardware lists (grocery lists use `category`) */
	types?: Partial<Record<'pharmacy' | 'hardware', string>>
}

export interface CatalogCategory {
	key: string
	template: string
	icon: string
	names: Names
}

export const catalogVersion: string = data.version
export const categories: CatalogCategory[] = data.categories as CatalogCategory[]
export const items: CatalogItem[] = data.items as CatalogItem[]

const itemsByKey = new Map(items.map((i) => [i.key, i]))
const categoriesByKey = new Map(categories.map((c) => [c.key, c]))
const index = new Map<string, string>()
// Order of precedence (first item wins): English/German names, then their synonyms and brands, then
// the names in the other languages, so a word in another language never shadows an English/German
// term (French "Persil" is parsley, the German brand is detergent). Mirrors CatalogService.
const isPrimary = (code: string) => code === 'en' || code === 'de'
const indexTerm = (term: string, key: string) => {
	const n = normalize(term)
	if (n && !index.has(n)) index.set(n, key)
}
for (const item of items) {
	for (const [code, name] of Object.entries(item.names)) if (isPrimary(code) && name) indexTerm(name, item.key)
}
for (const item of items) {
	for (const terms of Object.values(item.synonyms ?? {})) for (const term of terms) indexTerm(term, item.key)
}
for (const item of items) {
	for (const [code, name] of Object.entries(item.names)) if (!isPrimary(code) && name) indexTerm(name, item.key)
}

export const getItem = (key: string | null | undefined) => (key ? itemsByKey.get(key) : undefined)
export const getCategory = (key: string) => categoriesByKey.get(key)

function lookup(norm: string): string | undefined {
	for (const v of variants(norm)) {
		const k = index.get(v)
		if (k) return k
	}
	return undefined
}

export function matchCatalog(name: string): { item: CatalogItem, exact: boolean } | null {
	const norm = normalize(name)
	if (norm === '') return null
	let key = lookup(norm)
	if (key) return { item: itemsByKey.get(key)!, exact: true }
	const words = norm.split(' ')
	for (let i = 1; i < words.length; i++) {
		key = lookup(words.slice(i).join(' '))
		if (key) return { item: itemsByKey.get(key)!, exact: false }
	}
	// German compounds: "Bio Vollmilchjoghurt" -> "...joghurt" (only longer tails to avoid noise)
	const last = words[words.length - 1]
	if (last.length > 6) {
		for (let i = 1; i <= last.length - 4; i++) {
			key = index.get(last.slice(i))
			if (key) return { item: itemsByKey.get(key)!, exact: false }
		}
	}
	return null
}

/** The identity key used to merge duplicates; same rule as ItemService::resolve(). */
export function identityKey(name: string): string {
	const m = matchCatalog(name)
	return m && m.exact ? `c:${m.item.key}` : normalize(name)
}

export const itemName = (item: CatalogItem, lang: Lang) => item.names[lang] ?? item.names.en
export const categoryName = (cat: CatalogCategory, lang: Lang) => cat.names[lang] ?? cat.names.en

/** Each list type has its own category set; "custom" lists use the grocery departments. */
export function listType(template: string): 'grocery' | 'pharmacy' | 'hardware' {
	return template === 'pharmacy' || template === 'hardware' ? template : 'grocery'
}

/** The categories of a list's type in default aisle order ("own items" first). Mirrors CatalogService. */
export function categoriesForTemplate(template: string): CatalogCategory[] {
	const type = listType(template)
	return categories.filter((c) => c.template === type || c.template === 'all')
}

export function isCategoryFor(key: string, template: string): boolean {
	const c = categoriesByKey.get(key)
	return !!c && (c.template === 'all' || c.template === listType(template))
}

/** Category of a catalog item on a list of the given template ("other" if that type has none). */
export function itemCategory(item: CatalogItem, template: string): string {
	const type = listType(template)
	return type === 'grocery' ? item.category : (item.types?.[type] ?? 'other')
}

function levenshtein(a: string, b: string, max: number): number {
	if (Math.abs(a.length - b.length) > max) return max + 1
	let prev = Array.from({ length: b.length + 1 }, (_, i) => i)
	for (let i = 1; i <= a.length; i++) {
		const cur = [i]
		let rowMin = i
		for (let j = 1; j <= b.length; j++) {
			cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1))
			rowMin = Math.min(rowMin, cur[j])
		}
		if (rowMin > max) return max + 1
		prev = cur
	}
	return prev[b.length]
}

interface SearchEntry { item: CatalogItem, term: string, primary: boolean, lang: Lang }
const searchIndex: SearchEntry[] = []
for (const item of items) {
	for (const [code, name] of Object.entries(item.names) as Array<[Lang, string]>) {
		searchIndex.push({ item, term: normalize(name), primary: true, lang: code })
	}
	for (const [code, terms] of Object.entries(item.synonyms ?? {}) as Array<[Lang, string[]]>) {
		for (const syn of terms) searchIndex.push({ item, term: normalize(syn), primary: false, lang: code })
	}
}

/**
 * Typeahead over the catalog. Scores: exact > prefix > word prefix > contains > fuzzy;
 * names in the UI language rank above English/German and synonyms; other languages are not searched
 * (their words would only add noise).
 */
export function searchCatalog(query: string, lang: Lang, limit = 12): CatalogItem[] {
	const q = normalize(query)
	if (q === '') return []
	const best = new Map<string, number>()
	for (const e of searchIndex) {
		if (e.lang !== lang && e.lang !== 'en' && e.lang !== 'de') continue
		let score = 0
		if (e.term === q) score = 100
		else if (e.term.startsWith(q)) score = 80 - Math.min(20, e.term.length - q.length)
		else if (e.term.includes(' ' + q)) score = 60
		else if (q.length >= 3 && e.term.includes(q)) score = 40
		else if (q.length >= 4 && levenshtein(q, e.term.slice(0, q.length + 1), 1) <= 1) score = 25
		if (score === 0) continue
		if (e.lang === lang) score += 8
		if (e.primary) score += 4
		if (score > (best.get(e.item.key) ?? 0)) best.set(e.item.key, score)
	}
	return [...best.entries()]
		.sort((a, b) => b[1] - a[1] || itemName(itemsByKey.get(a[0])!, lang).localeCompare(itemName(itemsByKey.get(b[0])!, lang)))
		.slice(0, limit)
		.map(([k]) => itemsByKey.get(k)!)
}
