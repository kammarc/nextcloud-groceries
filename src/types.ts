export type ItemId = number | string

export interface Item {
	id: ItemId
	listId: number
	catalogKey: string | null
	name: string
	normName: string
	category: string
	quantity: string | null
	note: string | null
	icon: string | null
	checked: boolean
	addedBy: string
	checkedBy: string | null
	checkedAt: number | null
	source: string
	sourceRef: string | null
	sourceLabel: string | null
	revision: number
	createdAt: number
	updatedAt: number
	/** not yet confirmed by the server */
	pending?: boolean
}

export interface Tombstone {
	id: number
	deleted: true
	revision: number
}

export type Permission = 'owner' | 'edit' | 'view'

export interface ListSummary {
	id: number
	name: string
	icon: string | null
	/** small data: URL picture instead of an emoji */
	image?: string | null
	color: string | null
	owner: string
	template: string
	autoClearHours: number
	revision: number
	metaRevision: number
	permission: Permission
	openCount?: number
	shared?: boolean
}

export interface Store {
	id: number
	name: string
	icon: string | null
	/** small data: URL picture instead of an emoji */
	image?: string | null
	order: string[]
}

export interface CustomCategory {
	key: string
	name: string
	icon: string | null
}

export interface Prefs {
	activeStoreId: number | null
	viewMode: 'list' | 'grid'
	/** show department headings (false = one big list/grid in aisle order) */
	grouped?: boolean
	/** show the "frequently bought" section in the add-items screen */
	showFrequent?: boolean
	/** shared lists: show "Added by …" under items others added */
	showAddedBy?: boolean
	/** draw item icons as one-colour pen sketches instead of colour emoji */
	sketchIcons?: boolean
}

export interface Meta {
	list: ListSummary
	stores: Store[]
	categories: CustomCategory[]
	overrides: Record<string, { category: string | null, icon: string | null }>
	prefs: Prefs
	people: Record<string, string>
}

export interface HistoryEntry {
	id: number
	name: string
	normName: string
	catalogKey: string | null
	category: string
	icon: string | null
	lastQuantity: string | null
	useCount: number
	lastUsedAt: number
}

export interface ChangesResponse {
	revision: number
	metaRevision: number
	full: boolean
	items: Array<Item | Tombstone>
	meta?: Meta
}

export interface Member {
	type: 'user' | 'group'
	id: string
	displayName: string
	permission: Permission
}

export interface ItemData {
	name?: string
	quantity?: string | null
	note?: string | null
	category?: string | null
	icon?: string | null
	checked?: boolean
	source?: string
	sourceRef?: string | null
	sourceLabel?: string | null
}

export type Op =
	| { opId: string, kind: 'create', listId: number, tempId: string, data: ItemData, at: number }
	| { opId: string, kind: 'update', listId: number, itemId: ItemId, data: ItemData, at: number }
	| { opId: string, kind: 'delete', listId: number, itemId: ItemId, at: number }
	| { opId: string, kind: 'checkout', listId: number, at: number }
