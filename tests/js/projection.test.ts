import { describe, expect, it } from 'vitest'
import { project, resolveLocal, type ResolveContext } from '../../src/sync/projection'
import { buildSections, categoryOrder } from '../../src/lib/sorting'
import type { Item, Op } from '../../src/types'

const ctx: ResolveContext = { overrides: {}, history: new Map(), customCategories: new Set(), template: 'grocery' }

function item(id: number, name: string, extra: Partial<Item> = {}): Item {
	const r = resolveLocal(name, null, ctx)
	return {
		id, listId: 1, catalogKey: r.catalogKey, name, normName: r.normName, category: r.category, quantity: null, note: null,
		icon: r.icon, checked: false, addedBy: 'anna', checkedBy: null, checkedAt: null, source: 'manual', sourceRef: null,
		sourceLabel: null, revision: 1, createdAt: 0, updatedAt: 0, ...extra,
	}
}

const op = (o: Record<string, unknown>): Op => ({ opId: Math.random().toString(), at: 1_700_000_000_000, listId: 1, ...o } as Op)

describe('resolveLocal', () => {
	it('uses catalog identity and category', () => {
		expect(resolveLocal('Bananen', null, ctx)).toMatchObject({ normName: 'c:bananas', category: 'produce' })
	})
	it('prefers per-list overrides', () => {
		const c = { ...ctx, overrides: { 'c:oat_milk': { category: 'drinks', icon: null } } }
		expect(resolveLocal('Hafermilch', null, c).category).toBe('drinks')
	})
	it('falls back to other', () => {
		expect(resolveLocal('Zauberpulver', null, ctx)).toMatchObject({ normName: 'zauberpulver', category: 'other' })
	})
})

describe('project (optimistic state)', () => {
	const server = { 1: item(1, 'Milch', { quantity: '1 l' }), 2: item(2, 'Brot', { checked: true }) }

	it('adds new items as pending', () => {
		const out = project(server, [op({ kind: 'create', tempId: 'tmp-a', data: { name: 'Äpfel' } })], 1, ctx, 'me')
		expect(out.find((i) => i.id === 'tmp-a')).toMatchObject({ name: 'Äpfel', category: 'produce', pending: true })
	})

	it('merges duplicates into the open item like the server does', () => {
		const out = project(server, [op({ kind: 'create', tempId: 'tmp-a', data: { name: 'Milch', quantity: '500 ml' } })], 1, ctx, 'me')
		expect(out).toHaveLength(2)
		expect(out.find((i) => i.id === 1)?.quantity).toBe('1.5 l')
	})

	it('reopens a ticked item instead of duplicating it', () => {
		const out = project(server, [op({ kind: 'create', tempId: 'tmp-a', data: { name: 'Brot' } })], 1, ctx, 'me')
		expect(out.find((i) => i.id === 2)?.checked).toBe(false)
	})

	it('applies updates addressed to a temp id that was merged', () => {
		const out = project(server, [
			op({ kind: 'create', tempId: 'tmp-a', data: { name: 'Milch' } }),
			op({ kind: 'update', itemId: 'tmp-a', data: { checked: true } }),
		], 1, ctx, 'me')
		expect(out.find((i) => i.id === 1)).toMatchObject({ checked: true, checkedBy: 'me' })
	})

	it('deletes and checks out', () => {
		expect(project(server, [op({ kind: 'delete', itemId: 1 })], 1, ctx, 'me').map((i) => i.id)).toEqual([2])
		expect(project(server, [op({ kind: 'checkout' })], 1, ctx, 'me').map((i) => i.id)).toEqual([1])
	})

	it('ignores ops of other lists', () => {
		expect(project(server, [op({ kind: 'delete', itemId: 1, listId: 2 })], 1, ctx, 'me')).toHaveLength(2)
	})
})

describe('aisle sorting', () => {
	const store = { id: 1, name: 'Migros', icon: null, order: ['drinks', 'produce'] }

	it('default order follows the store-department list, own items first', () => {
		const order = categoryOrder('grocery', null, [])
		expect(order.slice(0, 4)).toEqual(['other', 'produce', 'bakery', 'cereals'])
		expect(order).toContain('vegan')
		expect(order).toContain('international')
		expect(order.filter((k) => ['laundry', 'cleaning', 'paper'].includes(k))).toEqual(['laundry', 'cleaning', 'paper'])
	})

	it('puts the store order first, then the remaining departments', () => {
		const order = categoryOrder('grocery', store, [{ key: 'custom_x', name: 'Bio-Laden', icon: null }])
		expect(order.slice(0, 3)).toEqual(['drinks', 'produce', 'other'])
		expect(order[order.length - 1]).toBe('custom_x')
		expect(new Set(order).size).toBe(order.length)
	})

	it('groups items into sections in store order, sorted by name', () => {
		const items = [item(1, 'Wasser'), item(2, 'Bananen'), item(3, 'Äpfel'), item(4, 'Zauberpulver'), item(5, 'Weichspüler')]
		const sections = buildSections(items, 'grocery', store, [], 'de')
		expect(sections.map((s) => s.key)).toEqual(['drinks', 'produce', 'other', 'laundry'])
		expect(sections[1].items.map((i) => i.name)).toEqual(['Äpfel', 'Bananen'])
		expect(sections[0].name).toBe('Getränke')
		expect(sections[2].name).toBe('Eigene Artikel')
	})

	it('pharmacy and hardware lists only use their own category sets', () => {
		const pharmacy = categoryOrder('pharmacy', null, [])
		expect(pharmacy[0]).toBe('other')
		expect(pharmacy).toContain('pharmacy_pain')
		expect(pharmacy).not.toContain('dairy')
		expect(pharmacy).not.toContain('care')
		const hardware = categoryOrder('hardware', null, [])
		expect(hardware).toContain('tools')
		expect(hardware).not.toContain('produce')
		expect(categoryOrder('custom', null, [])).toContain('produce')
	})

	it('items are sorted into the category set of the list type', () => {
		const pharmacy = { ...ctx, template: 'pharmacy' }
		expect(resolveLocal('Ibuprofen', null, ctx).category).toBe('care')
		expect(resolveLocal('Ibuprofen', null, pharmacy).category).toBe('pharmacy_pain')
		expect(resolveLocal('Milch', null, pharmacy).category).toBe('other')
		expect(resolveLocal('Schrauben', null, { ...ctx, template: 'hardware' }).category).toBe('fasteners')
		expect(resolveLocal('Ibuprofen', 'dairy', pharmacy).category).toBe('pharmacy_pain')
	})
})
