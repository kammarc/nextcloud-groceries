import type { CustomCategory, Item, ItemData } from '../types'
import type { ItemSheetValue } from '../components/ItemSheet.vue'

/** What the long-press item sheet (components/ItemSheet.vue) shows. */
export interface ItemSheetEntry {
	name: string
	icon?: string | null
	catalogKey?: string | null
	category: string
	quantity?: string | null
	existing?: boolean
	inList?: boolean
	template: string
	custom: CustomCategory[]
}

/**
 * Heights of the item sheet, as fractions of the screen. Pulled up, it is exactly as high as its
 * content (contentPx, measured by ItemSheetModal), at most 90 %. The first stop shows amount and
 * button: it was 0.62 with a note field (56 px + 12 px gap) below the amount; notes are gone, so it
 * ends that much lower on every screen height.
 */
const NOTE_FIELD_PX = 68
const MAX = 0.9

export function itemSheetStops(contentPx: number): { first: number, list: number[] } {
	const h = typeof window !== 'undefined' ? window.innerHeight : 800
	const full = contentPx > 0 ? Math.min(MAX, contentPx / h) : MAX
	const first = Math.min(full, Math.max(0.4, 0.62 - NOTE_FIELD_PX / h))
	// hardly any difference: a single height
	if (full - first < 0.03) return { first: full, list: [0, full] }
	return { first, list: [0, first, full] }
}

/** What the item sheet changed on an item that is already on the list. */
export function itemChanges(item: Item, v: ItemSheetValue): ItemData {
	const d: ItemData = {}
	if (v.name !== item.name) d.name = v.name
	if (v.quantity !== (item.quantity ?? null)) d.quantity = v.quantity
	if (v.category !== item.category) d.category = v.category
	if (v.icon !== (item.icon ?? null)) d.icon = v.icon
	return d
}
