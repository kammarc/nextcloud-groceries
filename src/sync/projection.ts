/**
 * Optimistic UI: the visible list = last server state + pending (not yet synced) operations.
 * Re-applied after every poll, so local changes never flicker away while they are in flight.
 */
import { isCategoryFor, itemCategory, matchCatalog } from '../lib/catalog'
import { normalize } from '../lib/normalize'
import { mergeQuantity } from '../lib/quantity'
import type { HistoryEntry, Item, ItemData, ItemId, Meta, Op } from '../types'

export interface ResolveContext {
	overrides: Meta['overrides']
	history: Map<string, HistoryEntry>
	customCategories: Set<string>
	/** list template: decides which category set applies */
	template: string
}

const validCategory = (key: string | null | undefined, ctx: ResolveContext): key is string =>
	!!key && (isCategoryFor(key, ctx.template) || ctx.customCategories.has(key))

/** Client-side copy of ItemService::resolve() (category precedence: explicit > override > history > catalog). */
export function resolveLocal(name: string, explicitCategory: string | null | undefined, ctx: ResolveContext) {
	const match = matchCatalog(name)
	const normName = match && match.exact ? `c:${match.item.key}` : normalize(name)
	const override = ctx.overrides[normName]
	const hist = ctx.history.get(normName)
	let category: string | null = validCategory(explicitCategory, ctx) ? explicitCategory : null
	if (!category && override && validCategory(override.category, ctx)) category = override.category
	if (!category && hist && hist.category !== 'other' && validCategory(hist.category, ctx)) category = hist.category
	category ??= match ? itemCategory(match.item, ctx.template) : 'other'
	return {
		normName,
		catalogKey: match?.item.key ?? null,
		category,
		icon: override?.icon ?? match?.item.icon ?? null,
	}
}

export function project(server: Record<string, Item>, ops: Op[], listId: number, ctx: ResolveContext, uid: string): Item[] {
	const items = new Map<string, Item>()
	for (const [k, v] of Object.entries(server)) items.set(k, v)
	const alias = new Map<string, string>()
	const key = (id: ItemId) => alias.get(String(id)) ?? String(id)

	for (const op of ops) {
		if (op.listId !== listId) continue
		switch (op.kind) {
			case 'create': {
				const d = op.data
				const r = resolveLocal(d.name ?? '', d.category, ctx)
				const existing = [...items.values()].find((i) => i.normName === r.normName && !i.checked)
					?? [...items.values()].find((i) => i.normName === r.normName)
				if (existing) {
					const merged: Item = existing.checked
						? { ...existing, checked: false, checkedBy: null, checkedAt: null, name: d.name ?? existing.name, quantity: d.quantity ?? null, note: d.note ?? null, pending: true }
						: { ...existing, quantity: mergeQuantity(existing.quantity, d.quantity), note: appendNote(existing.note, d.note ?? null), pending: true }
					items.set(String(existing.id), merged)
					alias.set(op.tempId, String(existing.id))
				} else {
					items.set(op.tempId, {
						id: op.tempId,
						listId,
						catalogKey: r.catalogKey,
						name: d.name ?? '',
						normName: r.normName,
						category: r.category,
						quantity: d.quantity ?? null,
						note: d.note ?? null,
						icon: d.icon ?? r.icon,
						checked: false,
						addedBy: uid,
						checkedBy: null,
						checkedAt: null,
						source: d.source ?? 'manual',
						sourceRef: d.sourceRef ?? null,
						sourceLabel: d.sourceLabel ?? null,
						revision: Number.MAX_SAFE_INTEGER,
						createdAt: Math.floor(op.at / 1000),
						updatedAt: Math.floor(op.at / 1000),
						pending: true,
					})
				}
				break
			}
			case 'update': {
				const k = key(op.itemId)
				const cur = items.get(k)
				if (cur) items.set(k, applyUpdate(cur, op.data, uid, op.at, ctx))
				break
			}
			case 'delete':
				items.delete(key(op.itemId))
				break
			case 'checkout':
				for (const [k, v] of items) if (v.checked) items.delete(k)
				break
		}
	}
	return [...items.values()]
}

function applyUpdate(cur: Item, d: ItemData, uid: string, at: number, ctx: ResolveContext): Item {
	const next: Item = { ...cur, pending: true }
	if (d.name !== undefined && d.name !== cur.name) {
		const r = resolveLocal(d.name, d.category, ctx)
		Object.assign(next, { name: d.name, normName: r.normName, catalogKey: r.catalogKey, category: r.category, icon: r.icon })
	}
	if (d.quantity !== undefined) next.quantity = d.quantity || null
	if (d.note !== undefined) next.note = d.note || null
	if (d.category) next.category = d.category
	if (d.icon !== undefined) next.icon = d.icon || null
	if (d.checked !== undefined) {
		next.checked = d.checked
		next.checkedBy = d.checked ? uid : null
		next.checkedAt = d.checked ? Math.floor(at / 1000) : null
	}
	return next
}

function appendNote(a: string | null, b: string | null): string | null {
	if (!a) return b
	if (!b || a.includes(b)) return a
	return `${a}; ${b}`
}
