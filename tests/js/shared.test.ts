import { describe, expect, it } from 'vitest'
import cases from '../fixtures/shared-cases.json'
import { normalize } from '../../src/lib/normalize'
import { parseLine } from '../../src/lib/parser'
import { mergeQuantity } from '../../src/lib/quantity'
import { matchCatalog } from '../../src/lib/catalog'

describe('normalize (parity with PHP NameNormalizer)', () => {
	for (const [input, expected] of cases.normalize) {
		it(`${input} -> ${expected}`, () => expect(normalize(input)).toBe(expected))
	}
})

describe('parseLine (parity with PHP IngredientParser)', () => {
	for (const c of cases.parse) {
		it(c.line, () => expect(parseLine(c.line)).toEqual({ quantity: c.quantity, name: c.name, note: c.note }))
	}
})

describe('mergeQuantity (parity with PHP QuantityMerger)', () => {
	for (const [a, b, out] of cases.merge as [string | null, string | null, string][]) {
		it(`${a} + ${b} = ${out}`, () => expect(mergeQuantity(a, b)).toBe(out))
	}
})

describe('matchCatalog (parity with PHP CatalogService)', () => {
	for (const [name, key, exact] of cases.match as [string, string | null, boolean][]) {
		it(`${name} -> ${key}`, () => {
			const m = matchCatalog(name)
			expect(m?.item.key ?? null).toBe(key)
			if (key !== null) expect(m?.exact).toBe(exact)
		})
	}
})
