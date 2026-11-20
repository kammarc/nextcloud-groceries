import { defineStore } from 'pinia'
import { del, get, set } from 'idb-keyval'
import { api, ApiError, isTransient } from '../api/client'
import { project, resolveLocal, type ResolveContext } from '../sync/projection'
import type { ChangesResponse, HistoryEntry, Item, ItemData, ItemId, ListSummary, Meta, Op, Prefs, Store } from '../types'

interface ListState {
	revision: number
	metaRevision: number
	items: Record<string, Item>
	meta: Meta | null
	history: HistoryEntry[]
	lastSync: number
}

interface CacheShape {
	user: { id: string, displayName: string } | null
	lists: ListSummary[]
	states: Record<number, ListState>
	outbox: Op[]
	/** list order changed while offline, not yet saved on the server */
	pendingListOrder?: number[] | null
}

const CACHE_KEY = 'groceries:cache:v1'

export function uuid(): string {
	if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
		try { return crypto.randomUUID() } catch { /* insecure context */ }
	}
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		const r = Math.random() * 16 | 0
		return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
	})
}

const emptyState = (): ListState => ({ revision: 0, metaRevision: 0, items: {}, meta: null, history: [], lastSync: 0 })

let persistTimer: ReturnType<typeof setTimeout> | null = null

export const useData = defineStore('data', {
	state: () => ({
		user: null as CacheShape['user'],
		lists: [] as ListSummary[],
		listsLoaded: false,
		states: {} as Record<number, ListState>,
		outbox: [] as Op[],
		online: typeof navigator === 'undefined' ? true : navigator.onLine,
		flushing: false,
		lastError: null as string | null,
		hydrated: false,
		pendingListOrder: null as number[] | null,
	}),

	getters: {
		uid: (s) => s.user?.id ?? '',
		pendingCount: (s) => s.outbox.length,
		list: (s) => (id: number) => s.lists.find((l) => l.id === id) ?? s.states[id]?.meta?.list ?? null,
		state: (s) => (id: number): ListState => s.states[id] ?? emptyState(),
	},

	actions: {
		// ---- persistence ----------------------------------------------------------------------
		async hydrate() {
			if (this.hydrated) return
			try {
				const cache = await get<CacheShape>(CACHE_KEY)
				if (cache) {
					this.user = cache.user
					this.lists = cache.lists ?? []
					this.states = cache.states ?? {}
					this.outbox = cache.outbox ?? []
					this.pendingListOrder = cache.pendingListOrder ?? null
				}
			} catch {
				// no IndexedDB (private mode): work online only
			}
			this.hydrated = true
		},

		persist() {
			if (persistTimer) clearTimeout(persistTimer)
			persistTimer = setTimeout(() => {
				const snapshot: CacheShape = JSON.parse(JSON.stringify({
					user: this.user, lists: this.lists, states: this.states, outbox: this.outbox,
					pendingListOrder: this.pendingListOrder,
				}))
				set(CACHE_KEY, snapshot).catch(() => {})
			}, 300)
		},

		/** Logout / revoked device: forget everything of the account on this device. */
		async reset() {
			if (persistTimer) clearTimeout(persistTimer)
			persistTimer = null
			this.$reset()
			this.hydrated = true
			await del(CACHE_KEY).catch(() => {})
		},

		// ---- lists ----------------------------------------------------------------------------
		async loadLists() {
			if (this.pendingListOrder) await this.saveListOrder()
			const res = await api<{ user: { id: string, displayName: string }, lists: ListSummary[] }>('GET', '/lists')
			this.user = res.user
			this.lists = res.lists
			this.listsLoaded = true
			this.online = true
			this.persist()
			return res.lists
		},

		/** New order of the lists screen (drag and drop): applied at once, saved per user on the server. */
		async reorderLists(ids: number[]) {
			const pos = new Map(ids.map((id, i) => [id, i]))
			this.lists = [...this.lists].sort((a, b) => (pos.get(a.id) ?? ids.length) - (pos.get(b.id) ?? ids.length))
			this.pendingListOrder = this.lists.map((l) => l.id)
			this.persist()
			await this.saveListOrder()
		},

		async saveListOrder() {
			const ids = this.pendingListOrder
			if (!ids) return
			try {
				await api('PUT', '/list-order', { ids })
				if (this.pendingListOrder === ids) this.pendingListOrder = null
				this.persist()
			} catch (e) {
				if (!isTransient(e)) this.pendingListOrder = null
			}
		},

		async createList(data: { name: string, template: string, icon?: string | null, color?: string | null, image?: string | null }) {
			const list = await api<ListSummary>('POST', '/lists', data)
			this.lists.push(list)
			this.persist()
			return list
		},

		async updateList(id: number, data: Partial<Pick<ListSummary, 'name' | 'icon' | 'image' | 'color' | 'template' | 'autoClearHours'>>) {
			const list = await api<ListSummary>('PUT', `/lists/${id}`, data)
			const i = this.lists.findIndex((l) => l.id === id)
			if (i >= 0) this.lists[i] = { ...this.lists[i], ...list }
			await this.syncList(id)
			return list
		},

		async deleteList(id: number) {
			await api('DELETE', `/lists/${id}`)
			this.forgetList(id)
		},

		async leaveList(id: number) {
			await api('DELETE', `/lists/${id}/members/me`)
			this.forgetList(id)
		},

		forgetList(id: number) {
			this.lists = this.lists.filter((l) => l.id !== id)
			delete this.states[id]
			this.outbox = this.outbox.filter((o) => o.listId !== id)
			this.persist()
		},

		// ---- sync -----------------------------------------------------------------------------
		/**
		 * Returns the *reactive* state of a list, creating it if needed.
		 * (`a ?? (this.states[id] = x)` would hand back the raw object, whose mutations don't re-render.)
		 */
		ensureState(listId: number): ListState {
			if (!this.states[listId]) this.states[listId] = emptyState()
			return this.states[listId]
		},

		resolveContext(listId: number): ResolveContext {
			const st = this.state(listId)
			return {
				overrides: st.meta?.overrides ?? {},
				history: new Map(st.history.map((h) => [h.normName, h])),
				customCategories: new Set((st.meta?.categories ?? []).map((c) => c.key)),
				template: this.list(listId)?.template ?? 'grocery',
			}
		},

		/** The visible items: server state with pending local changes applied. */
		items(listId: number): Item[] {
			const st = this.state(listId)
			return project(st.items, this.outbox, listId, this.resolveContext(listId), this.uid)
		},

		/** Sends pending changes, then pulls everything that changed since our last revision. */
		async syncList(listId: number) {
			await this.flush()
			const st = this.ensureState(listId)
			const res = await api<ChangesResponse>('GET', `/lists/${listId}/changes?since=${st.revision}`)
			this.online = true
			if (res.full) st.items = {}
			for (const it of res.items) {
				if ('deleted' in it && it.deleted) delete st.items[String(it.id)]
				else st.items[String(it.id)] = it as Item
			}
			st.revision = res.revision
			st.metaRevision = res.metaRevision
			if (res.meta) {
				st.meta = res.meta
				const i = this.lists.findIndex((l) => l.id === listId)
				if (i >= 0) this.lists[i] = { ...this.lists[i], ...res.meta.list }
			}
			const firstSync = st.lastSync === 0
			st.lastSync = Date.now()
			if (firstSync) this.loadHistory(listId).catch(() => {})
			this.persist()
		},

		async loadHistory(listId: number) {
			const history = await api<HistoryEntry[]>('GET', `/lists/${listId}/history`)
			const st = this.ensureState(listId)
			st.history = history
			this.persist()
			return history
		},

		async forgetHistory(listId: number, historyId: number) {
			const st = this.states[listId]
			if (st) st.history = st.history.filter((h) => h.id !== historyId)
			await api('DELETE', `/lists/${listId}/history/${historyId}`)
			this.persist()
		},

		// ---- outbox ---------------------------------------------------------------------------
		enqueue(op: Op) {
			this.outbox.push(op)
			this.persist()
			this.flush().catch(() => {})
		},

		async flush() {
			if (this.flushing) return
			this.flushing = true
			try {
				while (this.outbox.length > 0) {
					const op = this.outbox[0]
					try {
						await this.send(op)
						this.online = true
						this.lastError = null
					} catch (e) {
						if (isTransient(e)) {
							this.online = false
							return
						}
						if (e instanceof ApiError && e.status === 401) {
							return
						}
						// permanent failure (validation, item gone, no permission): drop the change
						this.lastError = (e as Error).message
					}
					this.outbox.shift()
					this.persist()
				}
			} finally {
				this.flushing = false
			}
		},

		async send(op: Op) {
			const st = this.ensureState(op.listId)
			const upsert = (item: Item | { id: number, deleted: true }) => {
				if ('deleted' in item) delete st.items[String(item.id)]
				else st.items[String(item.id)] = item
			}
			switch (op.kind) {
				case 'create': {
					const res = await api<{ item: Item }>('POST', `/lists/${op.listId}/items`, { ...op.data, opId: op.opId })
					upsert(res.item)
					// later ops that referenced the temporary id now target the real one
					for (const o of this.outbox) {
						if ((o.kind === 'update' || o.kind === 'delete') && o.itemId === op.tempId) o.itemId = res.item.id
					}
					break
				}
				case 'update': {
					if (typeof op.itemId === 'string') return // its create failed permanently
					const res = await api<{ item: Item }>('PATCH', `/lists/${op.listId}/items/${op.itemId}`, op.data)
					upsert(res.item)
					break
				}
				case 'delete':
					if (typeof op.itemId === 'string') return
					await api('DELETE', `/lists/${op.listId}/items/${op.itemId}`)
					delete st.items[String(op.itemId)]
					break
				case 'checkout':
					await api('POST', `/lists/${op.listId}/checkout`)
					for (const [k, v] of Object.entries(st.items)) if (v.checked) delete st.items[k]
					break
			}
		},

		// ---- item actions (optimistic) --------------------------------------------------------
		addItem(listId: number, data: ItemData): string {
			const tempId = 'tmp-' + uuid()
			this.enqueue({ opId: uuid(), kind: 'create', listId, tempId, data, at: Date.now() })
			this.bumpHistory(listId, data)
			return tempId
		},

		updateItem(listId: number, itemId: ItemId, data: ItemData) {
			this.enqueue({ opId: uuid(), kind: 'update', listId, itemId, data, at: Date.now() })
		},

		setChecked(listId: number, itemId: ItemId, checked: boolean) {
			this.updateItem(listId, itemId, { checked })
		},

		/** Returns an undo function. */
		deleteItem(listId: number, item: Item): () => void {
			const opId = uuid()
			this.enqueue({ opId, kind: 'delete', listId, itemId: item.id, at: Date.now() })
			return () => {
				const i = this.outbox.findIndex((o) => o.opId === opId)
				if (i >= 0) {
					this.outbox.splice(i, 1)
					this.persist()
				} else {
					this.addItem(listId, { name: item.name, quantity: item.quantity, note: item.note, category: item.category, icon: item.icon })
				}
			}
		},

		checkout(listId: number) {
			this.enqueue({ opId: uuid(), kind: 'checkout', listId, at: Date.now() })
		},

		/** Keeps suggestions fresh offline; the server keeps the authoritative history. */
		bumpHistory(listId: number, data: ItemData) {
			const st = this.states[listId]
			if (!st || !data.name) return
			const r = resolveLocal(data.name, data.category, this.resolveContext(listId))
			const now = Math.floor(Date.now() / 1000)
			const existing = st.history.find((h) => h.normName === r.normName)
			if (existing) {
				existing.useCount++
				existing.lastUsedAt = now
			} else {
				st.history.unshift({
					id: -Date.now(), name: data.name, normName: r.normName, catalogKey: r.catalogKey, category: r.category,
					icon: data.icon ?? r.icon, lastQuantity: data.quantity ?? null, useCount: 1, lastUsedAt: now,
				})
			}
		},

		// ---- preferences, stores, categories --------------------------------------------------
		async setPrefs(listId: number, prefs: Partial<Prefs>) {
			const st = this.states[listId]
			if (st?.meta) st.meta.prefs = { ...st.meta.prefs, ...prefs }
			this.persist()
			try {
				await api('PUT', `/lists/${listId}/prefs`, prefs)
			} catch (e) {
				if (!isTransient(e)) throw e
			}
		},

		async createStore(listId: number, name: string, icon: string | null, order?: string[], image: string | null = null) {
			const store = await api<Store>('POST', `/lists/${listId}/stores`, { name, icon, order, image })
			await this.syncList(listId)
			return store
		},

		async updateStore(listId: number, storeId: number, data: Partial<Store>) {
			const s = this.states[listId]?.meta?.stores.find((x) => x.id === storeId)
			if (s) Object.assign(s, data)
			await api<Store>('PUT', `/lists/${listId}/stores/${storeId}`, data)
			await this.syncList(listId)
		},

		async deleteStore(listId: number, storeId: number) {
			await api('DELETE', `/lists/${listId}/stores/${storeId}`)
			const st = this.states[listId]
			if (st?.meta?.prefs.activeStoreId === storeId) st.meta.prefs.activeStoreId = null
			await this.syncList(listId)
		},

		async createCategory(listId: number, name: string, icon: string | null) {
			const c = await api<{ key: string }>('POST', `/lists/${listId}/categories`, { name, icon })
			await this.syncList(listId)
			return c.key
		},
	},
})
