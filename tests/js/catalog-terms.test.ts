import { describe, expect, it } from 'vitest'
import { items, matchCatalog } from '../../src/lib/catalog'

/**
 * Every name, synonym and brand of a catalog item must find that item. A term that resolves to a
 * different item is claimed twice (catalog/items.txt or catalog/brands.txt) and would be mis-filed.
 */
// known homographs after umlaut folding: German "Säge" (saw) == English "sage" (herb)
const KNOWN = new Set(['saw:Säge'])

describe('catalog terms', () => {
	it('every term matches its own item', () => {
		const conflicts: string[] = []
		for (const item of items) {
			const terms = [...Object.values(item.names), ...Object.values(item.synonyms ?? {}).flat()]
			for (const term of new Set(terms)) {
				if (KNOWN.has(`${item.key}:${term}`)) continue
				const m = matchCatalog(term)
				if (!m || !m.exact) conflicts.push(`${item.key}: "${term}" matches nothing exactly`)
				else if (m.item.key !== item.key) conflicts.push(`${item.key}: "${term}" belongs to ${m.item.key}`)
			}
		}
		expect(conflicts).toEqual([])
	})

	it.each([
		['Nutella', 'chocolate_spread'],
		['Zewa', 'paper_towels'],
		['Tempo', 'tissues'],
		['Persil', 'laundry_detergent'],
		['Hakle', 'toilet_paper'],
		['Pampers', 'diapers'],
		['Rinderhack', 'ground_beef'],
		['2 Packungen Spülmaschinentabs', 'dishwasher_tabs'],
		['Knorr Brühe', 'stock_cubes'],
		['Coca-Cola', 'cola'],
	])('"%s" -> %s', (term, key) => {
		expect(matchCatalog(term)?.item.key).toBe(key)
	})
})
