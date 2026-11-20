import { categoriesForTemplate, categoryName, getCategory, type Lang } from './catalog'
import type { CustomCategory, Item, Store } from '../types'

export interface Section {
	key: string
	name: string
	icon: string
	items: Item[]
}

/**
 * Category order for a list: the active store's aisle order first, then remaining categories
 * in the template's default order, then the list's custom categories.
 */
export function categoryOrder(template: string, store: Store | null | undefined, custom: CustomCategory[]): string[] {
	const all = [...categoriesForTemplate(template).map((c) => c.key), ...custom.map((c) => c.key)]
	if (!store || store.order.length === 0) return all
	const seen = new Set<string>()
	const out: string[] = []
	for (const k of store.order) {
		if (!seen.has(k)) { seen.add(k); out.push(k) }
	}
	return [...out, ...all.filter((k) => !seen.has(k))]
}

export function categoryLabel(key: string, custom: CustomCategory[], lang: Lang): { name: string, icon: string } {
	const c = getCategory(key)
	if (c) return { name: categoryName(c, lang), icon: c.icon }
	const cc = custom.find((x) => x.key === key)
	if (cc) return { name: cc.name, icon: cc.icon ?? '🏷️' }
	const other = getCategory('other')!
	return { name: categoryName(other, lang), icon: other.icon }
}

/** Groups open items into sections in aisle order; items sorted by name within a section. */
export function buildSections(items: Item[], template: string, store: Store | null | undefined, custom: CustomCategory[], lang: Lang): Section[] {
	const order = categoryOrder(template, store, custom)
	const rank = new Map(order.map((k, i) => [k, i]))
	const groups = new Map<string, Item[]>()
	for (const item of items) {
		const k = rank.has(item.category) ? item.category : 'other'
		if (!groups.has(k)) groups.set(k, [])
		groups.get(k)!.push(item)
	}
	const collator = new Intl.Collator(lang, { sensitivity: 'base', numeric: true })
	return [...groups.entries()]
		.sort((a, b) => (rank.get(a[0]) ?? 999) - (rank.get(b[0]) ?? 999))
		.map(([key, list]) => ({
			key,
			...categoryLabel(key, custom, lang),
			items: list.sort((a, b) => collator.compare(a.name, b.name)),
		}))
}
